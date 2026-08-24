#!/bin/bash
# Runs once, on first initialisation of the mysql data volume.
set -eu

# SMF creates its own tables as InnoDB/utf8mb4, but the database's own default is
# what anything created outside that path inherits. Pinning it means it cannot
# drift out from under the forum, the same reason the postgres side pins
# standard_conforming_strings.
#
# The collation is left to whatever utf8mb4 defaults to on this server, because
# that is what SMF's tables get: its DDL sets CHARSET but never COLLATE.
mysql --protocol=socket -uroot -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
	ALTER DATABASE \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4;
EOSQL

echo "[smf-dev] database ${MYSQL_DATABASE} initialised"
