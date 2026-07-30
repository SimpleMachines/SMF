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

To reinstall from scratch: `docker compose down -v`, delete `Settings.php` and
`Settings_bak.php`, then `docker compose up -d`.

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
```
