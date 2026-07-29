@AGENTS.md

## Claude Code

Claude Code does not read `AGENTS.md` on its own, so the import above pulls it in. Keep
the shared instructions there and only Claude-specific notes here.

- The Bash tool runs Git Bash on Windows, which rewrites `/var/www/html/...` arguments
  into Windows paths. Use the PowerShell tool for `docker exec ... php /var/www/html/...`,
  or the call will fail with "Could not open input file".
- Prefer `Grep`/`Glob` over shelling out to `rg`/`find`, and read `Sources/Db/Schema/v3_0/`
  before trusting any column name in a query.
