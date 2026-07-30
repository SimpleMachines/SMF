# The SMF 2.1 baseline

A **baseline** is a fully installed SMF 2.1 forum, filled with realistic
content, dumped for both MySQL and PostgreSQL, and committed. It exists so that
testing the SMF 2.1 → 3.0 upgrade can start from a known, identical forum in
seconds instead of an afternoon of clicking through an installer.

Everything here builds one, and nothing here is needed to *use* one — restoring
is a single command.

## Use a baseline

```sh
docker compose up -d --build
.docker/baseline/restore.sh --engine mysql --profile small
```

Then browse <http://localhost:8180> and log in as `admin` / `baseline`.

`restore.sh` loads the dump, unpacks the attachments, renders `Settings.php`
from the shipped template, and then checks every row count against the manifest.
That last step is the point: a dump that loads without error is not the same as
a dump that arrived intact.

## Build a new one

```sh
.docker/baseline/make-baseline.sh --engine both --profile small
```

Per engine, that runs: reset → install → populate → extras → dump → restore.
The restore at the end is not a convenience; a dump is not finished until it has
been proven to load. Expect four to eight minutes per engine.

Nothing is committed automatically. The script prints the `git add` line and
stops.

## Sizes

| Profile | Members | Topics | Messages | Dump | Committed |
| --- | ---: | ---: | ---: | ---: | :-: |
| `tiny` | 50 | ~150 | 600 | ~1 MB | yes |
| `small` | 400 | ~1 200 | 6 000 | ~10 MB | yes |
| `medium` | 2 000 | ~8 000 | 40 000 | ~70 MB | no |
| `large` | 20 000 | ~40 000 | 250 000 | ~450 MB | no |

`tiny` is for a quick upgrade run; `small` is the realistic one, with enough
content for pagination, multi-page topics and per-member post counts to mean
something. `medium` and `large` are generated on demand and gitignored — the
`.gitignore` rule is keyed on the directory name, so producing one cannot
accidentally add 400 MB to the repository.

Topic counts are approximate. Populate.php does not build topics directly: each
message starts a new one with a probability of topics ÷ messages, so the result
lands near the target rather than on it. The ratio is what shapes the forum —
it decides how long the average thread is.

## How the pieces fit

```
make-baseline.sh          one command, runs everything below
 ├─ reset.sh              empties one engine's database, restages the installer
 ├─ install-forum.sh      drives install.php with curl -- no browser
 ├─ populate.sh           fetches, patches and runs upstream Populate.php
 │   ├─ patch-populate.php
 │   └─ run-populate.php
 ├─ run-extras.php        everything Populate.php does not create
 │   └─ extras/*.php
 ├─ dump.sh               mysqldump / pg_dump + manifest.php
 └─ restore.sh            loads it back and verifies it (verify.php)

lib.sh                    shared settings: profiles, credentials, pinned secrets
bootstrap.php             loads SMF from the CLI and signs in as the admin
db.php                    small read-only probe for the shell scripts
pin-settings.php          fixes the random secrets, writes Settings.baseline.php.tpl
```

## Installing without a browser

SMF 2.1's installer turns out to be almost stateless: it reads the step number
from `?step=`, then runs steps in order inside that one request, stopping at the
first that still needs input. There is no CSRF token and no server-side
progress. Five POSTs carry it end to end:

| Request | Runs | Stops at |
| --- | --- | --- |
| `?step=0` | Welcome, writable check | Database settings |
| `?step=2` | Database settings | Forum settings |
| `?step=3` | Forum settings, **database population** | Population report |
| `?step=4` | (acknowledges the report) | Admin account |
| `?step=5` | Admin account, finalise | done |

Three things about that are not obvious:

- **`DatabasePopulation` always returns false, even when it succeeds.** It
  builds the schema and then pauses so a human can read its "N duplicate tables
  ignored" report. Its form posts `pop_done`, which is the short-circuit that
  lets the next request walk past it. That is why there are five requests and
  not four.
- **`dbsession` and `reg_mode` must travel with `boardurl`.** Forum settings
  returning true falls straight through into database population, which reads
  them from the same `$_POST`.
- **`DeleteInstall` deletes nothing.** The congratulations page has a checkbox
  that points the browser at `?delete`; that request is what removes
  `install.php` and the two schema files.

The driver never sends the `stats` field, which would register the site with
simplemachines.org. A throwaway container has no business phoning home.

## Populate.php

Content comes from
[SimpleMachines/tools](https://github.com/SimpleMachines/tools) — the same
`Populate.php` a developer would open in a browser. It is **fetched at a pinned
commit and checksummed**, not vendored: it is third-party licensed (MPL 1.1,
with a BSD lorem ipsum generator) and does not belong in an SMF release branch.
The pin lives at the top of `lib.sh`.

To move to a newer upstream commit: change `POPULATE_COMMIT`, run
`populate.sh --fetch-only`, and paste the checksum it reports into
`POPULATE_SHA256`.

`patch-populate.php` makes four edits, each of which asserts it matched exactly
once so that an upstream change breaks the build loudly rather than quietly
producing a different baseline:

1. and 2. Neutralise the two top-level statements, so requiring the file defines
   the classes and nothing else. `run-populate.php` then loads SSI itself and
   constructs `Populate` with the profile's targets — the constructor already
   accepts an options array and copies it over its own defaults, so the
   hardcoded counters never need rewriting.
3. and 4. Guard two `mt_rand()` calls that are **fatal on PHP 8**, which rejects
   a minimum above its maximum where PHP 7 quietly swapped them. Both fire on
   the very first item, so unpatched the tool cannot run at all on a modern PHP.

Two further quirks are handled in `run-populate.php` rather than by patching:

- It signs in as the administrator. Populate.php was written for a browser
  session and leans on that: `createBoard()` cannot see the board it just made
  when `{query_see_board}` is a guest's, and `registerMember()` in admin mode
  calls `is_not_guest()` outright.
- It clamps each block to what is left. `makeMessages()` never increments its
  own counter, so an unclamped block always runs to full size — on the tiny
  profile that is the difference between 600 messages and 1001.

## The extras

Populate.php builds categories, boards, membergroups, members, topics and
messages. That is a forum, but it is not a forum an upgrade finds interesting:
SMF 3.0 runs 87 `v2_1` migrations and 17 `v3_0` ones, and almost none of them
touch those six tables. Each script in `extras/` seeds one area and says in its
header which migrations it is there to exercise.

| Script | What it adds | Why |
| --- | --- | --- |
| `05-board-access` | opens the generated boards to members | `createBoard()` leaves `member_groups` empty, so every board Populate makes is admin-only and the forum looks empty |
| `10-ips` | IPv4, IPv6 and absent addresses on messages, members and logins | the CLI run has no `REMOTE_ADDR`, so without this the whole baseline has no IPs and a dozen IPv6 migrations run against nothing |
| `20-profile-fields` | custom fields and their values, theme options, collapsed categories | 2.1 stores field *values* in the `themes` table as `cust_<name>`; unpicking that is what `CustomFieldsPart1-3` do |
| `30-content` | polls, PMs and labels, drafts, likes, mentions, edited posts, moved topics, look-alike names | one migration each, and a fresh install has none of it |
| `35-attachments` | real files on disk plus their rows, including a thumbnail and an avatar | `LegacyAttachments`, `AttachmentSizes` and `AttachmentDirectory` have nothing to do against a forum where nobody ever attached anything |
| `40-calendar` | four events and three holidays | a holiday dated in the sentinel year 1004 is 2.1's "every year", and turning that into a recurrence rule is the whole of `HolidaysToEvents` |
| `50-logs` | error, action, online, reported, flood, spider and search logs | every one has an IP column with its own migration, and all are empty on a fresh install |
| `60-admin` | bans (including an IPv6 range), a package, legacy settings, member columns | `DropTimeOffset`, `DropModPrefs`, `RemoveCookieTime` and `MailType` are no-ops unless the old values are actually present |
| `70-engine-quirks` | MyISAM tables on MySQL; assertions on PostgreSQL | a 2.1 install on a modern server creates *everything* as InnoDB, so `ConvertToInnoDb` would find nothing to convert |

Each script records a marker in `{db_prefix}settings`, so it travels inside the
dump and a restored baseline knows what it already contains. Re-running is
therefore safe; recovering from a script that failed *part way* is not, and the
fix is `reset.sh` and a fresh build.

## utf8mb3, on purpose

The MySQL server default is `utf8mb3`, and so is the database's. SMF 2.1's DDL
emits `DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci` — that is utf8mb3, not
utf8mb4; `$db_mb4` in `Settings.php` is the opt-in and it defaults to null.

Matching the server to that makes this a genuine pre-utf8mb4 2.1 forum, which is
exactly what SMF 3.0's UTF-8 conversion step has to convert. **Changing it to
utf8mb4 would quietly remove the thing under test.**

## The artifacts

```
artifacts/
  CURRENT                       the newest version, one line
  2.1.7-1/
    MANIFEST.md                 what it is, credentials, row counts
    manifest.json               the same, for scripts
    Settings.baseline.php.tpl       Settings.php with {{PLACEHOLDERS}}
    tiny/
      mysql.sql  postgres.sql  files-mysql.tgz  files-postgresql.tgz
    small/
      ...
```

Named `<smf version>-<baseline revision>`. Regenerating writes a **new**
directory and bumps `CURRENT`; it never overwrites, so a test can pin a revision
and history stays honest.

Some flags in `dump.sh` are load-bearing:

- **`--hex-blob`** is not optional. `poster_ip`, `member_ip`, `member_ip2`,
  `ban_items.ip_low`/`ip_high` and every log table's `ip` are `VARBINARY(16)`
  holding raw `inet_pton()` output. Dumped as text they embed NUL bytes and
  stray carriage returns, and the result neither restores nor survives git.
- **`--databases` with `--add-drop-database`** keeps the `CREATE DATABASE …
  utf8mb3` line, which is itself part of what the upgrade has to handle.
- **`--format=plain`** for PostgreSQL, not custom: plain text is what git can
  compress, and it restores with nothing but `psql`, so there is no version skew
  between the client that wrote it and the one that reads it.
- **`--skip-dump-date`** and `--quote-all-identifiers` keep re-dumps stable, so
  a regenerated baseline differs only where the data actually differs.

Both dumps are taken **inside the database containers**, writing to the
`/artifacts` bind mount. Two reasons, and both produce a broken artifact if
ignored: the web image ships `pg_dump` 15, which refuses to dump a PostgreSQL 17
server; and streaming a dump back through `docker compose exec` without `-T`
gets it a pseudo-TTY, which turns every `\n` into `\r\n` and silently corrupts
the file. `dump.sh` checks for carriage returns afterwards anyway.

### Pinned secrets

`ForumSettings()` generates `$auth_secret` and `$image_proxy_secret` with
`random_bytes()` and stores them nowhere but `Settings.php`. Since it is the
*database* that gets committed, a restored baseline paired with fresh random
secrets would invalidate every session, login cookie and two-factor backup code
inside it. So both are pinned to fixed values in `lib.sh`, and recorded in the
manifest.

They are dev-only values for a throwaway forum, published here deliberately.
Never reuse them.

### What is not reproducible

Regenerating a baseline produces different data, not identical data — the
content is randomly generated and the timestamps are real. That is fine, because
the committed dump *is* the fixed artifact; nothing re-derives it. The one value
worth knowing about is `sched_task_offset`, which the installer randomises into
the `settings` table and which therefore rides along inside the dump.

## Using this from the SMF 3.0 side

The later migration work lives on a 3.0 branch, and needs these artifacts. The
recommended way is `git archive` out of this same repository — no duplication, no
network, and it works from a fresh clone:

```sh
REF=$(cat .docker/baseline/baseline.ref)   # a pinned commit on docker-dev-env-2.1
git cat-file -e "$REF^{commit}" 2>/dev/null || git fetch origin docker-dev-env-2.1
rm -rf .docker/baseline/incoming && mkdir -p .docker/baseline/incoming
git archive "$REF" .docker/baseline/artifacts \
    | tar -x --strip-components=3 -C .docker/baseline/incoming
```

Pinning a commit rather than a branch name keeps the 3.0 side reproducible even
after this branch moves on.

The two alternatives are worse: copying the artifacts onto the 3.0 branch doubles
several megabytes of binary-ish blobs and creates two things that can drift, and
a shared directory outside the repository does not survive a fresh clone or CI.

## Credentials

| | |
| --- | --- |
| Administrator | `admin` / `baseline` |
| Email | `admin@example.com` |
| Database | `smf` / `smf`, root password `smf` |

Generated members cannot log in: `registerMember()` assigns them a random
password that is never recorded. Addresses are all `@example.com`, which RFC 2606
reserves, so nothing in a baseline can reach a real inbox even if a restored copy
is pointed at a live mail server by accident.
