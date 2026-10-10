# How to use this kit

1. Unzip and copy everything into your project folder, `~/Herd/construction-hris/`
   (the `.claude` folder is hidden on Mac — press Cmd+Shift+. in Finder to see it).
2. Commit: `git add CLAUDE.md .claude docs && git commit -m "Add PRD, tickets and Claude instructions"`
3. For each ticket, in order:
   - `git checkout -b T-01-baseline-config`
   - In VS Code, open a NEW Claude Code conversation, switch to Plan mode, type `/ticket T-01`
   - Review and approve the plan, review the changes, run `php artisan test`, check the browser
   - Commit, merge to `main`, push

Files:

- `CLAUDE.md` — project rules Claude reads every session
- `.claude/commands/ticket.md` — the `/ticket T-XX` command
- `docs/PRD.md` — the requirements
- `docs/TICKETS.md` — all tickets in one file (overview and index)
- `docs/tickets/` — one file per ticket, T-01 to T-19
