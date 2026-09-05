# SMF 2.1 development environment

A disposable SMF 2.1 forum in Docker: PHP and Apache, both database engines SMF
supports, a mail catcher, and a database browser.

This branch exists for one reason in particular -- to build the **baseline** that
SMF 2.1 -> 3.0 upgrade testing starts from. See
[`baseline/README.md`](baseline/README.md) for that. Everything on this page is
just the environment it runs in, which is also perfectly usable on its own.

## Requirements

Docker with Compose v2. Nothing else -- no PHP, no composer, no database on the
host.

## Start

```sh
docker compose up -d --build
```

Then open <http://localhost:8180/install.php> and work through the installer, or
skip the clicking:

```sh
docker compose exec -T web bash .docker/baseline/install-forum.sh
```

| | |
| --- | --- |
| Forum | <http://localhost:8180> |
| Adminer | <http://localhost:8181> |
| Mailpit | <http://localhost:8125> |
| MySQL | `localhost:3407` |
| PostgreSQL | `localhost:5533` |

Every port is offset from the SMF 3.0 environment's (8080/8081/8025/3307/5433),
so both stacks can run at the same time. That is deliberate: comparing 2.1 with
3.0, or migrating between them, means having both.

## Choosing the engine

Both databases are always running. `SMF_DB_TYPE` decides which one the forum is
pointed at:

```sh
SMF_DB_TYPE=postgresql docker compose up -d
```

It only affects the `Settings.php` the entrypoint *generates*. Once the forum is
installed, `Settings.php` wins and changing the variable does nothing but print
a note in the log. To actually move:

```sh
.docker/baseline/reset.sh --engine postgres
```

That throws away `Settings.php`, empties **that engine's** database, and stages
the installer again. The two engines keep separate volumes, so a forum can exist
on each at the same time and resetting one never touches the other.

## Installing by hand

If you use the browser installer rather than `install-forum.sh`, note that it
does not read its defaults from `Settings.php`. At *Database Server Settings*,
type the values the containers use, not the host ports:

| | MySQL | PostgreSQL |
| --- | --- | --- |
| Server | `mysql` | `postgres` |
| Port | leave blank | leave blank |
| User / password / name | `smf` / `smf` / `smf` | `smf` / `smf` / `smf` |

Afterwards, tick the box that deletes the installer.

## Everyday use

```sh
docker compose logs -f web                  # PHP errors and Apache logs
docker compose exec web bash                # a shell in the board directory
docker compose exec mysql mysql -usmf -psmf smf
docker compose exec postgres psql -U smf -d smf
docker compose down                         # stop
docker compose down -v                      # stop and destroy both databases
```

The checkout is bind-mounted, so edits on the host are live immediately. PHP
errors go to the container log rather than into the page -- `display_errors` is
off on purpose, because the baseline scripts parse the installer's HTML and its
CLI output, and a stray notice in either breaks them.

## Configuration

Everything has a working default, so no `.env` is needed. Copy
`.docker/env.example` to `.env` in the repository root to change ports,
versions or credentials.

Two versions are pinned rather than floating, and should stay that way:

- **MySQL 8.4.** MySQL 9 removes the `utf8`/`utf8mb3` aliases that SMF 2.1's
  DDL is written against.
- **PHP 8.2.** 2.1 supports 7.1 through 8.4, but 8.3 and later add deprecation
  notices that fill the log on every page.

The MySQL server default charset is `utf8mb3`, matching what 2.1's own DDL
creates. That is not an oversight -- see the note in
[`baseline/README.md`](baseline/README.md) about why it matters for upgrade
testing.

## What is in the image

`php:8.2-apache` plus the extensions `other/requirements.md` asks for -- pgsql,
mysqli, mbstring, fileinfo, gd, intl, curl, exif, ftp, xsl, zip, opcache -- and
the `mysql`, `psql` and `msmtp` command line tools.

There is deliberately **no composer**. SMF 2.1 has no runtime `vendor/`
directory; its only composer dependency is a dev-only build-tools package pulled
from a git repository, which the forum never loads.

## Files

```
compose.yaml                 the stack
.docker/php/Dockerfile       the PHP + Apache image
.docker/php/entrypoint.sh    waits for the database, writes Settings.php, stages the installer
.docker/php/php.ini          dev PHP settings
.docker/php/vhost.conf       Apache virtual host
.docker/php/msmtprc          routes mail() at Mailpit
.docker/mysql/init/          runs once, when the MySQL volume is created
.docker/postgres/init/       runs once, when the PostgreSQL volume is created
.docker/baseline/            the 2.1 baseline builder -- see its own README
```

Everything lives under a dot directory on purpose: SMF's file integrity checks
in CI skip those, so the development environment cannot break them.
