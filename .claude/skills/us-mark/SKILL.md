---
name: us-mark
description: Manually mark an upstream PR as backported or skipped
---

Load the upstream-sync skill and execute the **mark** subcommand.

Usage: `/us-mark <entry-id> <status> [reason] [--target=BRANCH]`

Where:

- `entry-id`: the upstream PR number / entry key
- `status`: `backported` or `skipped`
- `reason`: optional free-text reason (required for `skipped`)
- `--target=BRANCH`: which fork branch to update (default: `start`)

Examples:

- `/us-mark 123 backported`
- `/us-mark 123 backported --target=start-teams`
- `/us-mark 124 skipped "Not relevant — Breeze-only change"`
- `/us-mark 124 skipped "Not relevant" --target=start`
- `/us-mark 125 skipped "Reverted upstream"`

## Steps

1. Read state file.
2. Acquire lock (operation: "mark").
3. If entry exists in state, update the target slot. If not, fetch metadata from GitHub and create the entry manually with `sourceRefs.main` populated.
4. Execute:
   ```bash
   php .starter-kit/scripts/sync.php pr-set <entry-id> --target=<target> --status=<status> [--reason="..."]
   ```
5. Release lock.
6. Confirm: "Entry #<id> [{target}] marked as {status}."

Arguments: $ARGUMENTS
