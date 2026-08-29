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

## Reporting bugs

Issue reports come from `.github/ISSUE_TEMPLATE/standard_bug.yml`, which asks for steps to
reproduce, the expected and the actual result, and the versions involved. Two things about
them are easy to get wrong when the report is written by an agent that has just swept an
area and has several findings in hand.

- **One bug per report.** A sweep that turns up five defects files five reports, not one
  with a list in it. This was asked for directly, on #9520:

  > For future reference for AI agents: don't report two or more unrelated bugs in a
  > single issue report.

  The reason is what happens afterwards. An issue is closed by the pull request that
  fixes it, so a report carrying several unrelated bugs can never close honestly: either
  it shuts while some of what it describes is still broken, or it stays open long after
  the part somebody cared about is done. Splitting them also lets each one be picked up,
  labelled and argued about on its own. Where the findings really are related, file them
  separately and link them to each other.

- **Check whether a fix is already open before filing.** Searching pull request titles is
  not enough, because the fix often lives in a pull request that is about something else
  entirely. Search by the file instead:

  ```bash
  gh api "repos/SimpleMachines/SMF/pulls/<number>/files" --paginate --jq '.[].filename'
  ```

  Half of #9520 turned out to be fixed already by #9344, which is titled as a testing
  change and gives no hint that it touches the installer bug in question.

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

### Tests

There is a unit test suite. It is small and deliberately narrow, but where it reaches,
it is the only automated proof that a change does what it claims:

```bash
composer test        # or: vendor/bin/phpunit
```

CI runs it on every pull request, and on pushes to `release-3.0`, via
`.github/workflows/phpunit.yml`. Feature branches are only checked once they are in a PR,
so run it locally.

**The expectation: if the code you touched is reachable from this suite, your change
adds or updates a test in the same commit.** A bug fix lands as a regression test that
fails before the fix and passes after it, with a comment saying what went wrong — see
`SapiTest::testAPlainByteCountKeepsItsLastDigit()` for the shape. When the code is not
reachable, say so explicitly in the PR description rather than leaving it unsaid; do not
contort production code, add mocks or fake a database to force something under test.

#### When a test is possible

`tests/bootstrap.php` defines the constants `index.php` would define, points the
autoloader at `Sources/` and sets `Config::$boarddir`, `$sourcedir`, `$packagesdir`,
`$languagesdir`, `$cachedir` and `$language`. That is all. No `Settings.php`, no
database, no request. Within those limits the following are all testable, and each has a
worked example in `tests/Unit/`:

- **Pure and static helpers**: `Utils::buildRegex()`, `Sapi::memoryReturnBytes()`,
  `Security::hashPassword()`. Cheap to cover with a `#[DataProvider]`.
- **Value objects that parse or normalise a string**: `IP`, `Url`, `Uuid`,
  `TimeInterval`, `Punycode`. Construct one and assert on the result.
- **Class-level behaviour that needs no state**: late static binding, shared statics,
  what `Foo::load()` returns. `ActionTraitTest` is entirely this.
- **Protected and private helpers**, through `ReflectionMethod`, when the public entry
  point around them needs a database but the helper itself does not
  (`CreatePostNotifyTest::getTimeOffset()`).
- **Code that reads a few `Config::$modSettings` keys.** Set them in `setUp()` and
  `unset()` them in `tearDown()`. PHPUnit does not reset SMF's statics between tests, so
  a key left behind leaks into every test that follows.
- **Anything that only needs the language or Unicode data files**, since the bootstrap
  sets the paths they look in.

#### When it is not

- Anything calling `Db::$db` — there is no connection, and faking one is not worth it.
- Anything reading `User::$me`, the session, `$_GET`/`$_POST`/`$_SERVER`, or expecting a
  loaded theme or `Utils::$context`.
- Anything that emits output or sends headers. `beStrictAboutOutputDuringTests` is on, so
  a stray `echo` fails the test rather than being swallowed.

`failOnRisky` and `failOnWarning` are on as well: a test that asserts nothing is a
failure, not a pass.

#### Writing one

`tests/Unit/<Class>Test.php`, namespace `SMF\Tests\Unit`, `declare(strict_types=1)`,
extending `PHPUnit\Framework\TestCase`, with `#[CoversClass]` (or `#[CoversTrait]` for a
trait) on the class. Name the test after the behaviour, not the method —
`testItNormalisesIPv6ToItsShortestForm()`, not `testConstruct()`. New directories need
the usual `index.php` stub.

The code style rules apply to tests too, so run `composer lint-fix` on them. Two
consequences of the fixer worth knowing before you fight it:

- Data providers are `public static`, so `ordered_class_elements` moves them *below* the
  public test methods, into their own `Public static methods` banner.
- The `SMF/section_comments` fixer inserts a banner between an attribute and the method
  it belongs to. Do not let a method carrying `#[DataProvider]` be the first one in its
  group; `CreatePostNotifyTest` carries a note about this.

### Running the forum

The rest of CI only proves the code parses (`phplint` on 8.4 and 8.5) and is formatted.
So a fully green PR still tells you very little about whether a change works. Verify by
running the forum. The repository ships a Docker environment, documented in full in
`.docker/README.md`:

```bash
docker compose up -d --build
# http://localhost:8080          the forum (install.php on first run)
# http://localhost:8081          Adminer
# http://localhost:8025          Mailpit, catches all outgoing mail
# localhost:3307                 MySQL 8.4
# localhost:5433                 PostgreSQL 17
```

Both database services always start. `SMF_DB_TYPE` decides which one the generated
`Settings.php` points at, and it defaults to `mysql`. Because raw SQL has to work on
both engines, verify anything touching SQL on the other one as well: delete
`Settings.php` and `Settings_bak.php`, restart `web` with `SMF_DB_TYPE` set the other
way, then reinstall.

The checkout is bind-mounted into the web container, so edits are live with no rebuild.
Useful while working:

```bash
docker compose exec web php -l /var/www/html/Sources/Whatever.php

# Whichever engine the forum is installed on:
docker compose exec mysql mysql -usmf -psmf smf -e 'SELECT * FROM smf_log_errors ORDER BY id_error DESC LIMIT 5;'
docker compose exec postgres psql -U smf -d smf -c 'SELECT * FROM smf_log_errors ORDER BY id_error DESC LIMIT 5;'
```

`smf_log_errors` is the first place to look. Many failures are recorded there rather
than shown, especially anything in a background task.

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
- **`release-2.1` is not the same thing as released 2.1.** `Subs-Compat.php` exists to keep
  names that mods actually call working, and a mod can only call what shipped. Something
  added to the `release-2.1` branch after the last tag is not public API yet and does not
  need a shim, so check the tags rather than the branch:

  ```bash
  latest=$(git tag -l 'v2.1*' | sort -V | tail -1)
  git grep 'function the_name' "$latest" -- Sources/
  ```

  This was asked for directly, on #9535:

  > `make_fetch_safe()` never appeared in any released version of SMF 2.1 and never will,
  > so it doesnt need to be preserved in Subs-Compat.php

- **A removed check is not automatically a regression.** Some guards come out because they
  were wrong, so work out what the code is *for* before arguing one back in. The case that
  prompted this was `FtpConnection::passive()`, which had grown a check that the PASV
  address was globally routable. That reads like SSRF protection, and taken on its own it
  is. But `FtpConnection` is what the Package Manager uses to reach an FTP server the admin
  nominated, which is very often on the LAN or on localhost, so the check broke the ordinary
  case. From the same comment on #9535:

  > It was a mistake to check for global IP addresses in `FtpConnection::passive()`, which
  > is why that has been undone in this PR. When FtpConnection is used by the Package
  > Manager, connecting to local IP addresses is commonly needed and intended. The check
  > for global IP addresses only belongs in FtpFetcher, not FtpConnection.

  The general shape: a class that fetches from the open web and a class that talks to
  infrastructure the admin configured want opposite defaults. Put the check on the fetcher.

## Conventions to follow

- Match the surrounding code. Comment density here is high and conversational.
- Commit messages use the imperative-with-s form used upstream, for example
  "Ensures trailing chars are correctly quoted", "Only enforces bans that actually exist".
- Language strings live in `Languages/en_US/`; never hard-code user-facing text.
- Do not edit `vendor/`, `Packages/`, `Smileys/`, `cache/`, or `other/`.
- `Settings.php` is local configuration and is not committed.

### CSS naming

`php-cs-fixer` does not look at CSS, so nothing here is enforced automatically.
Reviewers do ask for it, so get it right the first time.

- **Class names are `snake_case`**, not kebab-case: `inner_wrap`, `navigate_section`,
  `content_wrapper`. In `Themes/default/css/index.css` this holds for 389 of the 392
  class selectors, and the three exceptions are all owned by third-party code
  (`.sceditor-container`, `.dz-image-preview`, `.g-recaptcha`), so they are not a
  precedent for new classes.
- **Custom properties are `kebab-case`**: `--body-bg`, `--primary-color-500`. Do not
  "correct" these to snake_case; the rule above is about class names only.
- The one place an underscore belongs in a custom property is a **variant suffix**
  appended to an otherwise kebab-case name, as `--component-property_variant`. The
  variant is usually a state (`--input-bg_hover`, `--button-border-color_active`, also
  `_focus` and `_disabled`), but the same slot carries other modifiers where a component
  needs them (`--progressbar-inner-bg_green`, `--genericbar-inner-box-shadow_vertical`).
  37 of the 246 custom properties use a suffix; the rest are plain kebab-case.
