# SMF development environment

A throwaway, reproducible local stack for working on SMF 3.0. Nothing here is
part of the shipped forum — it lives in `.docker/` precisely so the CI checks
(`check-smf-index.php`, `check-smf-license.php`) skip it.

Both database engines SMF supports are in the stack. **MySQL is the default.**

## Requirements

Docker Desktop (Linux containers). Nothing else — no local PHP, Composer, MySQL
or PostgreSQL install is needed.

## Start

```sh
docker compose up -d --build
```

First boot takes a few minutes: it builds the PHP image and runs
`composer install` into `vendor/`. Watch it with `docker compose logs -f web`
and wait for the `[smf-dev] ready` line.

| Service    | URL / address           | Notes                                   |
| ---------- | ----------------------- | --------------------------------------- |
| Forum      | http://localhost:8080   | The forum itself                        |
| Mailpit    | http://localhost:8025   | Every mail the forum sends lands here   |
| Adminer    | http://localhost:8081   | Database browser, pre-pointed at MySQL  |
| MySQL      | `localhost:3307`        | For a client on the host                |
| PostgreSQL | `localhost:5433`        | For a client on the host                |

Credentials are `smf` / `smf` / database `smf` on both engines.

## Choosing the engine

Both database services always start. `SMF_DB_TYPE` decides which one the forum
is pointed at, and it defaults to `mysql`:

```sh
# In .env, or inline:
SMF_DB_TYPE=postgresql docker compose up -d
```

This only affects the `Settings.php` the entrypoint *generates*. Once the forum
is installed, `Settings.php` is what counts and changing the variable does
nothing — the entrypoint says so in the log rather than leaving you guessing. To
move to the other engine, delete `Settings.php` and `Settings_bak.php`, then
restart `web` and reinstall.

Because both engines run side by side with separate volumes, you can install on
one, switch, install on the other, and switch back: each database keeps its own
forum.

## Installing the forum

```sh
.docker/install-forum.sh --engine mysql
.docker/install-forum.sh --engine postgresql
.docker/install-forum.sh --engine both
```

That resets the engine's database and installs a forum into it, with no browser
involved. It takes about a minute. Log in at http://localhost:8080 as
`admin` / `password`.

SMF 3.0's installer is CLI-native: `Maintenance::parseCliArguments()` turns
`--name=value` into `$_POST`, and `Maintenance::execute()` then runs every step
in one process, stopping at the first that still needs input. The script makes
two passes, because `databasePopulation()` always stops the first time even
though it succeeded — it pauses so a human can read its "N duplicate tables
ignored" report, and the form's `pop_done` field is the short-circuit past it.
Passing `pop_done` on the first pass would skip building the schema entirely.

It then deletes `install.php`, which the installer asks for but cannot do
itself — its `?delete` link is a GET, and command line arguments only ever reach
`$_POST`. That matters more than it sounds: while the file is there
`Settings.php` redirects every request back into the installer, and SMF puts a
"MAJOR SECURITY RISK" box on every page it shows an administrator. Reinstalling
still works, because `reset.sh` runs first and does not return until the
entrypoint has staged a fresh copy.

Two flags worth knowing:

- `--force` reinstalls even when a forum is already there. Without it the
  script leaves an existing install alone.
- `--pin-secrets` fixes `auth_secret` and `image_proxy_secret` to known values
  instead of the random ones `ForumSettings()` generates. Both installs then
  differ only in their database, so a login cookie survives `use-engine.sh`.
  Dev-only values for a throwaway forum: never reuse them.

### Two forums at once

`--engine both` installs MySQL first and PostgreSQL second, one after the other.
It has to be sequential: `Settings.php` pins a single `$db_type`, and
`Db::load()` hands back the connection it already made, so only one engine can
ever be live in a process.

Both installs are kept. Switch between them with:

```sh
.docker/use-engine.sh postgresql
```

That puts the saved `Settings.php` back and clears `cache/`. No restart is
needed — the entrypoint only writes `Settings.php` when there is not one, so it
leaves whatever is in place alone. The copies live in `.docker/settings/` and
are gitignored.

`reset.sh` is the other half: it empties one engine's database and restages the
installer, discarding that forum. `use-engine.sh` switches between forums,
`reset.sh` throws one away.

## Accounts and passwords

Two forums, each with its own administrator, and a password chosen months ago is
a recipe for an afternoon of hand written SQL. `user.sh` is there so it is not:

```sh
.docker/user.sh list
.docker/user.sh check admin 'password'
.docker/user.sh reset admin 'a new password'
```

`check` exits 0 when SMF would accept the password and 1 when it would not, so
it works in a conditional as well as by eye. It also points out an account that
is not activated, which fails to log in with a correct password and looks
exactly like a wrong one.

`--engine mysql|postgresql` reads the settings `use-engine.sh` saved for that
engine, so the *other* forum can be inspected without switching to it:

```sh
.docker/user.sh check admin 'password' --engine mysql
```

The hashing goes through SMF's own `Security` class rather than being written
here, so what `reset` puts in the table is by construction what `Login2` expects
to find. It clears `passwd_flood` at the same time: SMF locks an account out for
a while after enough wrong guesses, and a fresh password behind a lockout looks
exactly like a password that did not take.

### Installing in a browser instead

On first boot the entrypoint writes a `Settings.php` pre-filled for the chosen
engine and copies `other/install.php` to the web root, so
http://localhost:8080 redirects into the installer.

The installer does **not** read its form defaults from `Settings.php` — it uses
the hardcoded defaults in the database API class. On the *Database Server
Settings* step, enter:

| Field         | MySQL   | PostgreSQL   |
| ------------- | ------- | ------------ |
| Database type | `MySQL` | `PostgreSQL` |
| Server        | `mysql` | `postgres`   |
| Port          | `3306`  | `5432`       |
| Username      | `smf`   | `smf`        |
| Password      | `smf`   | `smf`        |
| Database name | `smf`   | `smf`        |

The internal ports are correct here: `3307` and `5433` are only how the *host*
reaches the databases from outside Docker. Containers talk to each other on the
compose network.

When the installer finishes, delete `install.php` from the repo root — while it
exists, `Settings.php` redirects every request back into the installer.

## Running CI locally

```sh
.docker/ci.sh              # everything CI checks
.docker/ci.sh --full       # style check over the whole tree, not just changes
.docker/ci.sh --fix        # apply the style fixes rather than reporting them
```

Mirrors `php.yml` (sign-off, the four file integrity checks, phplint) and
`php-cs-fixer.yml`, and runs the test suite when the branch has one. Every check
runs even after one fails, because finding out about the second problem on the
next push is the thing this is meant to stop.

`--full` is worth knowing about: the style workflow normally only looks at the
files a pull request changed, but switches to the whole tree when `composer.lock`
or the fixer config is in the diff. So a branch that touches a dependency
inherits every pre-existing violation in the repository. `--full` tells you that
before you push rather than after.

Two things it cannot do for you:

- **The other PHP version.** CI lints and tests on 8.4 *and* 8.5; the container
  is whichever built it. To cover the other:
  `PHP_VERSION=8.5 docker compose up -d --build web`.
- **The integration tests on both engines.** Use `.docker/test.sh` for that.

## Hardening the upgrade

The installer builds a 3.0 forum from `Sources/Db/Schema/v3_0/` in one go. The
upgrader arrives somewhere near the same place through a few hundred migrations
applied to whatever 2.1 left behind, and it has to survive two things that
happen to it constantly and that nothing checks: being run twice, and being cut
off half way.

```sh
BASE=../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/mysql.sql

.docker/rerun-upgrade.sh     --engine mysql --baseline "$BASE"
.docker/interrupt-upgrade.sh --engine mysql --baseline "$BASE"
```

Both rebuild the database for the engine they are given, so anything installed
on it is destroyed. The other engine is untouched. Both take a `--baseline` SQL
dump of a 2.1 forum; the one above is the committed baseline from the 2.1
development environment, and a dump of a real forum is a better test.

**`rerun-upgrade.sh`** upgrades, then upgrades again, and reports what the
second run changed. It should change nothing. Running the upgrader twice is not
an unusual thing to do -- it is what an admin does when a page times out, and it
is what every 3.0 patch upgrade does, since `VERSION_MAP` keys on an upper bound
and `3.0.99` selects the v3_0 migrations for any 3.0.x forum.

**`interrupt-upgrade.sh`** kills the upgrader part way through, starts it again,
and reports whether the forum it ends up with is the one an uninterrupted
upgrade would have built. By default it does this at five points across the run;
`--at N` picks one substep, `--points 10,50` picks a set. Kill points are given
as a percentage of an uninterrupted run's substeps, so the same numbers mean the
same places whatever the baseline is.

Recovery today means starting again from the top, over a database that is in
neither the old shape nor the new one, because the step, substep and start
position live in `$_GET` and nowhere else. `maintenance_tool_progress` in
`Settings.php` is the only thing written to disk, it holds the version the run
started from rather than where it had got to, and it is written by `preExit()`,
which a killed process never reaches. So every migration has to cope with a
half-migrated database, which is a stronger requirement than merely being safe
to repeat over a finished one.

`--backup` is worth adding to a run of it. The backup step is skipped on the
command line unless something asks for it, and a retry is exactly what
endangers what it produces: `backup_table()` opens with `DROP TABLE IF EXISTS`,
so a second pass replaces a good pre-upgrade copy with whatever the database
holds by then.

Both write their readings and reports under `.docker/rerun/` and
`.docker/interrupt/`, which are gitignored. Expect five to ten minutes per
upgrade, so a full sweep of kill points is the better part of an hour.

## Everyday use

```sh
docker compose logs -f web              # apache + php errors, live
docker compose logs -f postgres         # every failing query, with its SQL
docker compose exec web bash            # shell in the web container

docker compose exec mysql mysql -usmf -psmf smf    # mysql client
docker compose exec postgres psql -U smf           # psql

docker compose exec web composer install
docker compose exec web composer lint

docker compose up -d --build web        # after changing anything in .docker/php/
docker compose down                     # stop, keep both databases
docker compose down -v                  # stop and destroy both databases
```

`php.ini`, the vhost and the entrypoint are copied into the image at build time,
not bind-mounted, so a plain `restart` will not pick up edits to them. Rebuild.

The repository is bind-mounted at `/var/www/html`, so edits on the host are
live on the next request. Opcache is on but revalidates every request, so you
never need to restart for a PHP change.

To reinstall from scratch: `.docker/install-forum.sh --engine mysql --force`.
To wipe everything including the volumes: `docker compose down -v`.

## Comparing an upgrade against a fresh install

The installer builds the schema from `Sources/Db/Schema/v3_0/` in one go. The
upgrader arrives at the same place through a hundred-odd migrations applied to
whatever 2.1 left behind. They are meant to converge, and nothing checks that
they do:

```bash
.docker/compare-upgrade.sh --engine mysql --baseline path/to/a-2.1-dump.sql
```

That empties the database, loads the dump, upgrades it, reads the schema,
reinstalls from scratch, reads that too, and reports every place the two
disagree — a column of the wrong type, an index that was never created, a
primary key quietly dropped. It ends with the fresh install in place, and takes
five to ten minutes.

`--baseline` takes any SQL dump of a 2.1 database. A dump of a real forum is
the better test; the [2.1 development environment][baseline] builds a synthetic
one designed to hold something in every table an upgrade touches, which is
useful when you have no real forum to hand.

Two kinds of difference are reported but do not fail the run, because a real
forum always has some: the contents of `settings`, and the order columns sit in
within a table. Everything else is a schema difference and sets the exit code.

If the upgrade does not reach the end, the script stops there and says so
rather than comparing anyway. A half-upgraded database differs from a fresh
install in hundreds of places, all of them the honest consequence of the
migrations that never ran, and none of them worth reading.

On PostgreSQL the reading also covers sequences and the compatibility functions
SMF installs — `find_in_set()`, `instr()`, the `group_concat` aggregate and the
rest. A query naming one of those fails outright when it is not there, so a
missing function counts as a schema difference like a missing column does.

Both readings were checked name by name against the engine's own schema dump —
`pg_dump --schema-only` and `mysqldump --no-data --routines --triggers
--events` — and agree with them: 72 tables, 538 columns, 179 keys, and on
PostgreSQL 41 sequences and 19 functions besides. The only things either dump
reports that this does not are the `public` schema and the comment on it, and
the `AUTO_INCREMENT` counter, which measures how much a database has been used
rather than what shape it is.

The tool underneath is usable on its own, against any two SMF databases on the
same engine — two forums you already have, or the same forum before and after
something you are testing:

```bash
docker compose exec web php .docker/schema-tool.php dump --engine mysql --db smf > before.json
# ... do the thing ...
docker compose exec web php .docker/schema-tool.php dump --engine mysql --db smf > after.json
docker compose exec web php .docker/schema-tool.php diff before.json after.json
```

It talks to the database directly rather than through SMF, so it works on a
database SMF would refuse to run on — which is usually the one you want to look
at.

[baseline]: https://github.com/SimpleMachines/SMF/pull/9330

## Debugging SQL with the PostgreSQL log

The `postgres` log is the best tool in the stack for tracking down a broken
query. PostgreSQL logs every statement that errors together with the SQL that
caused it, always and without any configuration:

```
2026-01-01 12:00:00.000 UTC [98] ERROR:  relation "nope" does not exist at character 15
2026-01-01 12:00:00.000 UTC [98] STATEMENT:  select * from nope;
```

Nothing is written to a file inside the container, so the compose log above is
where to look. Add `--since 5m` to it to skip past the startup noise.

MySQL has no equivalent: it logs server errors only, never the client statement
that failed, so a query SMF gets wrong leaves no trace in its log. Since MySQL
is the default engine, a suspected SQL problem is worth reproducing against
PostgreSQL — install on it once and you can switch back and forth, because each
database keeps its own forum.

Only failing statements are logged. Successful ones, timings and connections
are not, so this shows you the queries that break, not the ones that merely
return the wrong thing.

## Configuration

`compose.yaml` works with no `.env` file. To change ports, versions, the engine
or credentials, copy `.docker/env.example` to `.env` in the repository root.

The `postgres` service also answers to the hostname `db`, which is what
`Settings.php` files generated before MySQL was added point at.

## What is in the image

PHP 8.4 on Apache, with everything `other/requirements.md` lists:

- Required: `mbstring`, `fileinfo`, and both `mysqli` and `pgsql` (SMF checks
  for `pg_connect`), so either engine can be chosen at install time.
- Recommended: `gd`, `intl`, `curl`, `exif`, `ftp`, `xsl`, and `zip`.
- Both database command line clients, for the `docker compose exec` recipes
  above and for the entrypoint's readiness check.
- `mail()` is routed through msmtp into Mailpit, so no mail can escape the
  machine.

Engine settings SMF asks for are pinned at server level rather than left to the
image defaults:

- PostgreSQL: `standard_conforming_strings = on`, as `requirements.md` requires.
- MySQL: `utf8mb4` and InnoDB, matching SMF's own table DDL. The collation is
  deliberately left at the charset default, because SMF sets `CHARSET` without
  `COLLATE`; forcing a different one here would diverge from the tables it
  creates.

## Files

```
compose.yaml                     the stack
.docker/php/Dockerfile           PHP + Apache image
.docker/php/php.ini              dev php settings, per requirements.md
.docker/php/vhost.conf           apache vhost
.docker/php/msmtprc              mail() -> mailpit
.docker/php/entrypoint.sh        composer install, Settings.php, permissions
.docker/mysql/init/10-smf.sh     runs once on first mysql database creation
.docker/postgres/init/10-smf.sh  runs once on first postgres database creation
.docker/env.example              optional overrides
.docker/lib.sh                   paths, credentials and engine names, shared
.docker/install-forum.sh         install a forum with no browser involved
.docker/reset.sh                 empty one engine and restage the installer
.docker/use-engine.sh            switch which installed forum is live
.docker/user.sh                  inspect accounts, check and reset passwords

.docker/upgrade-readings.sh      shared: driving upgrade.php, reading a database
.docker/rerun-upgrade.sh         upgrade twice, report what the second run changed
.docker/interrupt-upgrade.sh     kill an upgrade part way, report what recovery left
.docker/compare-upgrade.sh       upgrade a 2.1 dump, install 3.0, diff the two
.docker/schema-tool.php          read a database's shape, and compare readings
```
