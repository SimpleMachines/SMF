#!/bin/bash
# Runs once, on first initialisation of the mysql data volume.
set -eu

# SMF 2.1's DDL creates its tables as:
#
#     ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
#
# `utf8` there is utf8mb3, not utf8mb4 -- 2.1 treats utf8mb4 as an opt-in via
# $db_mb4 in Settings.php, which defaults to null. Pinning the database's own
# default to match means anything created outside SMF's DDL inherits the same
# encoding instead of quietly diverging, the same reason the postgres side pins
# standard_conforming_strings.
#
# This is also load-bearing for migration testing: a utf8mb3 database is what
# makes this a genuine pre-utf8mb4 2.1 forum, and converting it is precisely
# what SMF 3.0's UTF-8 conversion step has to do. Do not "modernise" this to
# utf8mb4 -- that would quietly remove the thing under test.
mysql --protocol=socket -uroot -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
	ALTER DATABASE \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
EOSQL

echo "[smf-2.1] database ${MYSQL_DATABASE} initialised (utf8mb3)"
