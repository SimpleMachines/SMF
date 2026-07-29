# AGENTS.md

Instructions for coding agents working on Simple Machines Forum (SMF).
Read by GitHub Copilot, Codex, Cursor, Gemini CLI and others directly; Claude Code
reads it through the `CLAUDE.md` in this directory.

Everything below is derived from the config files in this repository. When they
disagree with this document, the config files win.

## The project

- SMF 3.0 Alpha, PHP 8.4+ (`composer.json` pins the platform to 8.4.1; CI lints on 8.4 and 8.5).
- PSR-4: `SMF\` maps to `Sources/`, `SMF\Themes\` maps to `Themes/`.
- Entry points are `index.php`, `cron.php`, `SSI.php`, `proxy.php`, `subscriptions.php`.
- Database schema is defined in PHP, not SQL: `Sources/Db/Schema/v3_0/` (74 table classes),
  with migrations in `Sources/Maintenance/Migration/`.
- Both MySQL and PostgreSQL are supported. Any raw SQL must work on both.

## Branching and pull requests

- Branch from `release-3.0`. It is the main branch; `release-2.1` is maintenance only.
- One logical change per branch. The reviewers here prefer small, focused diffs.
- PR titles start with the target version: `[3.0] Short description in the imperative`.
- The PR body follows `.github/PULL_REQUEST_TEMPLATE/standard_pr.md`: a `#### Description`
  section and an `### Issues References (Fixes|Related|Closes)` section.

### Sign off every commit

SMF requires a Developer Certificate of Origin sign-off (`DCO.txt`). Commit with:

```bash
git commit -s
```

This appends `Signed-off-by: Name <email>`, which `check-signed-off.php` looks for on the
**last line** of the commit message. Two things to know:

- A trailer such as `Co-Authored-By:` after it will push the sign-off off the last line
  and the check will not find it. Keep `Signed-off-by:` last.
- The CI job can pass even when your commits are unsigned, because on a pull request it
  inspects a merge commit and walks up to the parents, where `release-3.0` is signed off.
  Verify locally instead:

```bash
php ./vendor/simplemachines/build-tools/check-signed-off.php; echo $?
```

## Code style

Run this before every commit; it is what CI checks:

```bash
composer lint       # check only, on files you changed
composer lint-fix   # apply the fixes
```

CI runs the same rules over changed files via `.github/workflows/php-cs-fixer.yml`.
The configuration is `.php-cs-fixer.dist.php`, based on `@PER-CS2x0`. The parts that
most often catch people out:

- **Tabs, not spaces**, width 4, LF line endings, final newline, no trailing whitespace
  (`.editorconfig`, and `.gitattributes` forces `eol=lf` even on Windows).
  YAML uses 2 spaces; `*.lock` uses spaces.
- **Short array syntax**, single quotes, `!=` rather than `<>`, no unused imports,
  imports ordered alphabetically (class, then function, then const).
- **Class members are ordered by the fixer**, via `ordered_class_elements`. Statics come
  *after* non-statics within each visibility, so the order for methods is
  `method_public`, `method_public_static`, `method_protected`, `method_private`,
  `method_protected_static`, `method_private_static`. Adding a protected *static* method
  next to the protected methods is a common way to fail the check.
- **Section banner comments are generated** by the custom `SMF/section_comments` fixer in
  `.github/phpcs/SectionComments.php`. Each group gets its own banner, for example
  `Public methods`, `Public static methods`, `Internal methods`,
  `Internal static methods`, `Class constants`, `Public properties`. Do not hand-write or
  reposition them; let `composer lint-fix` place them.

Docblocks are expected on classes, properties and methods, with tags ordered
param, throws, return.

## Verifying a change

There is a unit test suite, but it is small and deliberately narrow:

```bash
composer test        # or: vendor/bin/phpunit
```

It runs without a database, a `Settings.php` or a request: `tests/bootstrap.php` only
defines the constants `index.php` would define and points the autoloader at `Sources/`.
That covers pure helpers and class-level behaviour. **Anything reaching
`Config::$modSettings`, `User::$me` or `Db::$db` is out of scope**, which is most of the
forum. Add a test there when the code you are touching is reachable that way; do not
contort production code to make it testable.

The rest of CI only proves the code parses (`phplint` on 8.4 and 8.5) and is formatted.
So a fully green PR still tells you very little about whether a change works. Verify by
running the forum. The repository ships a Docker environment:

```bash
docker compose up -d --build
# http://localhost:8080          the forum (install.php on first run)
# http://localhost:8081          Adminer
# http://localhost:8025          Mailpit, catches all outgoing mail
# localhost:5433                 PostgreSQL 17
```

The checkout is bind-mounted into the web container, so edits are live with no rebuild.
Useful while working:

```bash
docker exec smf-dev-web-1 php -l /var/www/html/Sources/Whatever.php
docker exec smf-dev-db-1 psql -U smf -d smf -c 'SELECT * FROM smf_log_errors ORDER BY id_error DESC LIMIT 5;'
```

`smf_log_errors` is the first place to look. Many failures are recorded there rather
than shown, especially anything in a background task.

Some code is reachable with only the autoloader plus the constants that `index.php`
defines, which is enough to exercise pure helpers without a database. Anything that
touches `User::$me` or `Db::$db` needs a real request or fixtures.

## Things that bite in this codebase

- **Typed properties with no default throw when read before assignment.** Several are
  populated by a separate load step rather than a constructor, so an object obtained
  outside the usual request flow may not have them yet. Guard with `isset()` before
  reading, and do not "fix" it by adding a default: other code uses the uninitialised
  state as the signal to load.
- **Late static binding and shared statics.** A `static` property declared in a trait or
  parent class is shared with every descendant that does not redeclare it. Check
  `static::class` when the value is expected to be class-specific.
- **`empty()` and `isset()` hide errors.** `empty($obj->prop)` on a null `$obj` returns
  true silently, so a missing object reads as an empty value rather than failing. Check
  that the object exists before testing its properties.
- **Global state is everywhere**: `Config::$modSettings`, `User::$me`, `Db::$db`,
  `Utils::$context`, `Lang::$txt`, `Board::$info`, `Topic::$info`. Static properties such
  as `Topic::$info` are null when there is no current topic, which is not the same as an
  unapproved one.
- **Columns get dropped between versions.** Check the query you are editing against
  `Sources/Db/Schema/v3_0/` before trusting a column name. A query naming a removed column
  fails at runtime only, and inside a background task it retries forever and takes down
  unrelated page loads.
- **Deprecated compatibility layer**: `Sources/Subs-Compat.php` holds the old procedural
  API. It often shows the guard clauses the modern class methods should have; useful when
  tracking down a missing check.

## Conventions to follow

- Match the surrounding code. Comment density here is high and conversational.
- Commit messages use the imperative-with-s form used upstream, for example
  "Ensures trailing chars are correctly quoted", "Only enforces bans that actually exist".
- Language strings live in `Languages/en_US/`; never hard-code user-facing text.
- Do not edit `vendor/`, `Packages/`, `Smileys/`, `cache/`, or `other/`.
- `Settings.php` is local configuration and is not committed.
