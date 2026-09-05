#!/usr/bin/env bash
# Readings taken of an upgraded database, and the machinery for running the
# upgrader. Sourced by rerun-upgrade.sh and interrupt-upgrade.sh, never run.
#
# Both of those scripts ask the same question in different ways -- is the forum
# this upgrade produced the one it should be -- so both need the same answer to
# "what shape is this database in", and both need to drive upgrade.php the same
# way. Keeping one copy means a reading that learns to ignore something noisy
# learns it once.
#
# Expects lib.sh to have been sourced already, and $BOARD_DIR to be the working
# directory.
#
# shellcheck disable=SC2034

# The version this checkout is, which is what a finished upgrade has to leave
# in the database.
smf_version() {
	sed -n "s/.*define('SMF_VERSION', '\([^']*\)').*/\1/p" "$BOARD_DIR/index.php" | head -1
}

load_baseline() {
	local smf_type="$1" baseline="$2" service
	service=$(engine_service "$smf_type")

	if [ "$smf_type" = 'mysql' ]; then
		docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
			mysql -u"$DB_USER" -D "$DB_NAME" < "$baseline"
	else
		# ON_ERROR_STOP so that a dump taken from a database that is not empty,
		# or one taken with a different owner, stops here rather than producing
		# a half-loaded forum that then fails somewhere in the upgrader and
		# looks like a migration bug.
		docker compose exec -T "$service" \
			psql -v ON_ERROR_STOP=1 -q -U "$DB_USER" -d "$DB_NAME" < "$baseline" >/dev/null
	fi
}

# Runs the upgrader once, from the board directory, the way an admin would.
# $UPGRADE_ARGS is passed through to it, so a caller can ask for the parts the
# command line leaves off by default -- --backup above all, since the backup
# step is skipped unless something asks for it.
# Returns non-zero if it exited non-zero or stopped short of this version.
run_upgrade() {
	local smf_type="$1" log="$2" status=0 version

	# The upgrader ships in other/ and expects to be run from the board
	# directory, the same way install.php does. Nobody upgrading a real forum
	# has install.php sitting there, so neither does this.
	rm -f "$BOARD_DIR/install.php"
	cp "$BOARD_DIR/other/upgrade.php" "$BOARD_DIR/upgrade.php"

	docker compose exec -T web php upgrade.php ${UPGRADE_ARGS:-} > "$log" 2>&1 || status=$?

	rm -f "$BOARD_DIR/upgrade.php"

	if [ "$status" -ne 0 ]; then
		warn "${smf_type}: the upgrader exited ${status} -- ${log}"

		return 1
	fi

	# The upgrader reports a failed migration and stops, but still exits 0, so
	# the version in the database is the only thing that says whether it got to
	# the end. Checking it matters more than it looks: a half-upgraded database
	# differs from a finished one in hundreds of places, all of them the honest
	# consequence of the migrations that never ran, and none of them the kind of
	# difference these scripts exist to find.
	version=$(installed_version "$smf_type" || true)

	if [ "$version" != "$(smf_version)" ]; then
		warn "${smf_type}: the upgrade stopped at SMF ${version:-nothing}, expected $(smf_version)"
		warn "${smf_type}: the last thing it said is at the end of ${log}"

		return 1
	fi

	return 0
}

# ---------------------------------------------------------------- the readings
#
# Three readings, because the ways an upgrade goes wrong show up in different
# places. Each is written so that the same database always produces the same
# bytes, since anything varying on its own would be reported as a difference
# the upgrade caused.

# The shape of every table. Catches DDL that ran when it should not have: a
# column widened twice, an index added under a generated name, a primary key
# dropped and rebuilt differently, a table left half altered by a kill.
#
# AUTO_INCREMENT is stripped on MySQL. It is a property of the table rather
# than of its shape, it moves whenever a row is written, and the row counts
# below are the reading that would notice if rows had been inserted.
snapshot_schema() {
	local smf_type="$1" file="$2" service
	service=$(engine_service "$smf_type")

	if [ "$smf_type" = 'mysql' ]; then
		# --no-tablespaces because reading them wants the PROCESS privilege,
		# which the forum's own database user has no business holding.
		docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
			mysqldump -u"$DB_USER" --no-data --no-tablespaces --skip-comments \
			--skip-dump-date --skip-set-charset --routines --databases "$DB_NAME" \
			| sed -e 's/ AUTO_INCREMENT=[0-9]*//' > "$file"
	else
		docker compose exec -T "$service" \
			pg_dump -U "$DB_USER" -d "$DB_NAME" --schema-only --no-owner --no-privileges > "$file"
	fi

	[ -s "$file" ] || die "${smf_type}: the schema reading came back empty"
}

# How many rows are in each table. Catches the commonest data failure by far: a
# migration that inserts its settings, its permissions or its scheduled tasks
# without checking whether they are already there, and so doubles them.
snapshot_counts() {
	local smf_type="$1" file="$2" service table
	service=$(engine_service "$smf_type")

	if [ "$smf_type" = 'mysql' ]; then
		# The table list is read into an array first. Reading it with a pipe
		# into `while read` does not work: the docker client inside the loop
		# inherits the loop's stdin and swallows the rest of the list, so only
		# the first table is ever counted and the reading silently agrees with
		# itself no matter what changed.
		local -a tables
		mapfile -t tables < <(
			docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
				mysql -u"$DB_USER" -D "$DB_NAME" -N -B -e \
				"SELECT table_name FROM information_schema.tables
				 WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
				 ORDER BY table_name;" | tr -d '\r'
		)

		: > "$file"

		for table in "${tables[@]}"; do
			[ -n "$table" ] || continue

			printf '%s\t%s\n' "$table" "$(
				docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
					mysql -u"$DB_USER" -D "$DB_NAME" -N -B -e \
					"SELECT COUNT(*) FROM \`${table}\`;" | tr -d '\r'
			)" >> "$file"
		done
	else
		# One statement rather than a loop: a lateral join over the catalogue
		# counts every table without the script having to ask table by table.
		docker compose exec -T "$service" \
			psql -U "$DB_USER" -d "$DB_NAME" -tAX -F$'\t' -c \
			"SELECT c.relname, n.rows
			 FROM pg_class c
			 JOIN pg_namespace ns ON ns.oid = c.relnamespace
			 CROSS JOIN LATERAL (
				SELECT (xpath('/row/c/text()', query_to_xml(
					format('SELECT COUNT(*) AS c FROM %I.%I', ns.nspname, c.relname),
					false, true, ''
				)))[1]::text::bigint AS rows
			 ) n
			 WHERE c.relkind = 'r' AND ns.nspname = 'public'
			 ORDER BY c.relname;" > "$file"
	fi

	[ -s "$file" ] || die "${smf_type}: the row count reading came back empty"

	# Tables the forum writes to simply by being looked at, or that record the
	# upgrade itself. A difference in one of them says nothing about whether the
	# migrations did the right thing.
	sed -i -E '/^[a-z_]*(log_online|log_actions|log_errors|log_activity|log_floodcontrol|sessions|background_tasks)\t/d' "$file"
}

# Everything in the settings table. Catches a migration that rewrites a setting
# it should have left alone, which a row count cannot see because the number of
# rows does not change. Read as a table rather than a dump so the ordering is
# ours and not the engine's.
snapshot_settings() {
	local smf_type="$1" file="$2" service
	service=$(engine_service "$smf_type")

	if [ "$smf_type" = 'mysql' ]; then
		docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
			mysql -u"$DB_USER" -D "$DB_NAME" -N -B -e \
			"SELECT variable, value FROM ${DB_PREFIX}settings ORDER BY variable;" \
			| tr -d '\r' > "$file"
	else
		docker compose exec -T "$service" \
			psql -U "$DB_USER" -d "$DB_NAME" -tAX -F$'\t' -c \
			"SELECT variable, value FROM ${DB_PREFIX}settings ORDER BY variable;" > "$file"
	fi

	[ -s "$file" ] || die "${smf_type}: the settings reading came back empty"

	# Settings that move on their own. The times are written whenever the forum
	# is touched, and the counters are recalculated by the cleanup step.
	sed -i -E '/^(settings_updated|memberlist_updated|calendar_updated|last_mod_report_action|latestMember|latestRealName|latestMemberID|rand_seed|last_backup_at)\t/d' "$file"
}

# The columns of each backup_ table the backup step leaves behind.
#
# Read on its own rather than as part of the schema because these tables are the
# admin's only way back, and what threatens them is not a migration but a retry:
# backup_table() opens with DROP TABLE IF EXISTS, so a second backup pass
# replaces a good pre-upgrade copy with whatever the database holds at the time.
# A backup taken before the migrations still carries the columns 3.0 removes; one
# taken again afterwards does not, so the column list alone says which it is.
# How many rows they hold is already in the row count reading.
#
# Empty output is not an error. A run that was not asked to back up has no
# backup tables, and that is a legitimate reading of a database.
snapshot_backups() {
	local smf_type="$1" file="$2" service
	service=$(engine_service "$smf_type")

	if [ "$smf_type" = 'mysql' ]; then
		docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
			mysql -u"$DB_USER" -D "$DB_NAME" -N -B -e \
			"SELECT table_name, GROUP_CONCAT(column_name ORDER BY ordinal_position)
			 FROM information_schema.columns
			 WHERE table_schema = DATABASE() AND table_name LIKE 'backup\\_%'
			 GROUP BY table_name
			 ORDER BY table_name;" | tr -d '\r' > "$file"
	else
		docker compose exec -T "$service" \
			psql -U "$DB_USER" -d "$DB_NAME" -tAX -F$'\t' -c \
			"SELECT c.relname, string_agg(a.attname, ',' ORDER BY a.attnum)
			 FROM pg_class c
			 JOIN pg_namespace ns ON ns.oid = c.relnamespace
			 JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
			 WHERE c.relkind = 'r' AND ns.nspname = 'public' AND c.relname LIKE 'backup\_%'
			 GROUP BY c.relname
			 ORDER BY c.relname;" > "$file"
	fi
}

# Takes all the readings under one label.
snapshot() {
	local smf_type="$1" label="$2" out="$3"

	snapshot_schema   "$smf_type" "$out/${label}-${smf_type}.schema.sql"
	snapshot_counts   "$smf_type" "$out/${label}-${smf_type}.counts.tsv"
	snapshot_settings "$smf_type" "$out/${label}-${smf_type}.settings.tsv"
	snapshot_backups  "$smf_type" "$out/${label}-${smf_type}.backups.tsv"
}

# Compares two labelled snapshots, printing a line per reading and leaving the
# detail in $out/.compare for the caller to put in its report. Returns non-zero
# if any reading differs.
compare_snapshots() {
	local smf_type="$1" first="$2" second="$3" out="$4" status=0 what file

	: > "$out/.compare"

	for what in schema counts settings backups; do
		case "$what" in
			schema)   file='schema.sql' ;;
			counts)   file='counts.tsv' ;;
			settings) file='settings.tsv' ;;
			backups)  file='backups.tsv' ;;
		esac

		if diff -u "$out/${first}-${smf_type}.${file}" "$out/${second}-${smf_type}.${file}" > "$out/.diff" 2>&1; then
			printf '  %-10s unchanged\n' "$what"
		else
			printf '  %-10s CHANGED\n' "$what"
			{
				printf '  %s:\n' "$what"
				sed -e 's/^/    /' "$out/.diff"
				printf '\n'
			} >> "$out/.compare"
			status=1
		fi
	done

	rm -f "$out/.diff"

	return "$status"
}
