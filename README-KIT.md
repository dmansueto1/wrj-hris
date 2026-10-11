# How to use this kit

1. Copy `CLAUDE.md`, the `.claude` folder and the `docs` folder into your project root
   (the `.claude` folder is hidden on Mac — press Cmd+Shift+. in Finder to see it).
   Names in this kit already match your project: WRJ HRIS, `wrj_hris`, `wrj_hris_test`, `wrj-hris.test`.
2. For each ticket, in order:
   - Create the ticket branch from the latest `develop`.
   - In VS Code, open a NEW Claude Code conversation, switch to Plan mode, type `/ticket T-01`.
   - Review and approve the plan, review the changes, run `php artisan test`, check the browser.
   - Open ANOTHER new conversation and run `/review T-01`. Reply `fix` until the result is CLEAN or NITS ONLY.
   - Commit, push and merge into `develop` yourself. Claude never commits.

Files:

- `CLAUDE.md` — project rules Claude reads every session
- `.claude/commands/ticket.md` — the `/ticket T-XX` command (builds a ticket)
- `.claude/commands/review.md` — the `/review T-XX` command (reviews a built ticket against `develop`)
- `docs/PRD.md` — the requirements
- `docs/TICKETS.md` — all tickets in one file (overview and index)
- `docs/tickets/` — one file per ticket, T-01 to T-19
