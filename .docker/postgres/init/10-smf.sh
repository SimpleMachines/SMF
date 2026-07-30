#!/bin/bash
# Runs once, on first initialisation of the postgres data volume.
set -eu

# SMF requires standard_conforming_strings to be on (other/requirements.md).
# It is already the default on modern postgres; setting it at database level
# makes it explicit so it cannot drift out from under the forum.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
	ALTER DATABASE "$POSTGRES_DB" SET standard_conforming_strings = on;
EOSQL

echo "[smf-dev] database $POSTGRES_DB initialised"
