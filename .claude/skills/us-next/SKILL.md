---
name: us-next
description: Resolve and execute the next step in the upstream sync workflow (triage → backport → propagate → close). Use when the user says "next", "what's next", or wants to continue the sync without remembering which stage they're in.
---

# /us-next — Auto-advance the sync workflow

Single entry point that figures out where we are in the upstream sync state machine and runs the right step.

## How it works

Run the resolver:

```bash
php .starter-kit/scripts/sync.php next --execute
```

The `--execute` flag polls GitHub for any open downstream PRs and auto-marks them merged before deciding the next action.

The script picks the highest-priority pending action:

1. **Auto-update merged downstream PRs** (with `--execute`)
2. **Propagate** — backported PRs whose fork PR is merged but missing downstream coverage → run `/us-propagate <pr> <repo>`
3. **Wait** — backported PRs with open fork PR or open downstream PRs → just report
4. **Backport** — lowest-numbered pending PR → run `/us-backport <pr>`
5. **Triage** — queue empty → run `/us-triage`

## Workflow

1. Run `php .starter-kit/scripts/sync.php next --execute`.
2. Read the printed `Next step:` line — it names the slash command to run next.
3. Invoke that slash command (load the appropriate skill).
4. After it completes, the user can run `/us-next` again to advance further.

## Notes

- This is a thin wrapper. The state machine logic lives in `sync.php`.
- Use `php .starter-kit/scripts/sync.php status` for a full overview without taking action.
- If GitHub polling is slow or offline, drop `--execute` and `next` will still resolve based on local state.

$ARGUMENTS
