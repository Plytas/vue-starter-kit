---
name: us-next
description: Resolve and execute the next step in the upstream sync workflow (triage → backport → propagate → close). Use when the user says "next", "what's next", or wants to continue the sync without remembering which stage they're in.
---

# /us-next — Auto-advance the sync workflow

Single entry point that figures out where we are in the upstream sync state machine and runs the right step.

## How it works

Run the resolver:

```bash
php .starter-kit/scripts/sync.php next --execute [--target=start|start-teams|all]
```

Default: `--target=all` (walks `start` first, then `start-teams`).

The `--execute` flag polls GitHub for any open downstream PRs and auto-marks them merged before deciding the next action.

The resolver picks the highest-priority pending action in this order:

1. **Auto-update merged downstream PRs** (with `--execute`)
2. **Propagate** — backported entries whose fork PR is merged but missing downstream coverage → run `/us-propagate <entry> <repo>`
3. **Wait** — backported entries with open downstream PRs → just report
4. **Backport** — lowest-numbered pending entry (`start` before `start-teams`) → run `/us-backport <entry> [--target=BRANCH]`
5. **Triage** — queue empty → picks upstream branch with oldest `lastCheckedAt` watermark → run `/us-triage [--upstream-branch=<name>]`

## Workflow

1. Run `php .starter-kit/scripts/sync.php next --execute`.
2. Read the printed `Next step:` line — it names the slash command to run next.
3. Invoke that slash command (load the appropriate skill).
4. After it completes, run `/us-next` again to advance further.

## Scoped to a branch

```bash
# Only consider start-teams queue
php .starter-kit/scripts/sync.php next --target=start-teams
```

## Notes

- This is a thin wrapper. The state machine logic lives in `sync.php`.
- Use `php .starter-kit/scripts/sync.php status` for a full overview without taking action.
- If GitHub polling is slow or offline, drop `--execute` and `next` resolves from local state.

$ARGUMENTS
