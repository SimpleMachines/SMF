# SMF development environment (PostgreSQL)

A throwaway, reproducible local stack for working on SMF 3.0. Nothing here is
part of the shipped forum — it lives in `.docker/` precisely so the CI checks
(`check-smf-index.php`, `check-smf-license.php`) skip it.

## Requirements

Docker Desktop (Linux containers). Nothing else — no local PHP, Composer or
PostgreSQL install is needed.

## Start

```sh
docker compose up -d --build
```

First boot takes a few minutes: it builds the PHP image and runs
`composer install` into `vendor/`. Watch it with `docker compose logs -f web`
and wait for the `[smf-dev] ready` line.

| Service  | URL                     | Notes                                    |
| -------- | ----------------------- | ---------------------------------------- |
| Forum    | http://localhost:8080   | The forum itself                          |
| Mailpit  | http://localhost:8025   | Every mail the forum sends lands here     |
| Adminer  | http://localhost:8081   | Database browser, pre-pointed at `db`     |
| Postgres | `localhost:5433`        | For DBeaver/psql/etc. on the host         |

Database credentials are `smf` / `smf` / database `smf` throughout.

## Installing the forum

On first boot the entrypoint writes a `Settings.php` pre-filled for the
`db` service and copies `other/install.php` to the web root, so
http://localhost:8080 redirects into the installer.

The installer does **not** read its form defaults from `Settings.php` — it uses
the hardcoded defaults in `SMF\Db\APIs\PostgreSQL`. On the *Database Server
Settings* step you must enter:

| Field         | Value        |
| ------------- | ------------ |
| Database type | `PostgreSQL` |
| Server        | `db`         |
| Port          | `5432`       |
| Username      | `smf`        |
| Password      | `smf`        |
| Database name | `smf`        |

Port `5432` is correct here: `5433` is only how the host reaches postgres from
outside Docker. Containers talk to each other on the internal network.

When the installer finishes, delete `install.php` from the repo root — while it
exists, `Settings.php` redirects every request back into the installer.

## Everyday use

```sh
docker compose logs -f web          # apache + php errors, live
docker compose exec web bash        # shell in the web container
docker compose exec db psql -U smf  # psql on the forum database

docker compose exec web composer install
docker compose exec web composer lint

docker compose restart web          # after changing php.ini or the vhost
docker compose down                 # stop, keep the database
docker compose down -v              # stop and destroy the database
```

The repository is bind-mounted at `/var/www/html`, so edits on the host are
live on the next request. Opcache is on but revalidates every request, so you
never need to restart for a PHP change.

To reinstall from scratch: `docker compose down -v`, delete `Settings.php` and
`Settings_bak.php`, then `docker compose up -d`.

## Configuration

`compose.yaml` works with no `.env` file. To change ports, versions or
credentials, copy `.docker/env.example` to `.env` in the repository root.

## What is in the image

PHP 8.4 on Apache, with everything `other/requirements.md` lists:

- Required: `mbstring`, `fileinfo`, `pgsql` (SMF checks for `pg_connect`), plus
  `mysqli` so the installer still offers MySQL.
- Recommended: `gd`, `intl`, `curl`, `exif`, `ftp`, `xsl`, and `zip`.
- `standard_conforming_strings` is set `on` at database level, as SMF requires.
- `mail()` is routed through msmtp into Mailpit, so no mail can escape the
  machine.

## Files

```
compose.yaml                     the stack
.docker/php/Dockerfile           PHP + Apache image
.docker/php/php.ini              dev php settings, per requirements.md
.docker/php/vhost.conf           apache vhost
.docker/php/msmtprc              mail() -> mailpit
.docker/php/entrypoint.sh        composer install, Settings.php, permissions
.docker/postgres/init/10-smf.sh  runs once on first database creation
.docker/env.example              optional overrides
```
