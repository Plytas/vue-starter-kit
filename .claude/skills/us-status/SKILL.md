---
name: us-status
description: Show upstream sync status — pending, backported, and skipped PRs
---

Load the upstream-sync skill and execute the **status** subcommand.

Run:

```bash
php .starter-kit/scripts/sync.php status [--target=start|start-teams|all]
```

Default: `--target=all` (shows both sections).

Displays:
- **Upstream watermarks** header: one line per upstream branch (`main`, `teams`) showing last checked timestamp and SHA
- Per-target section for each fork branch in scope (`start`, `start-teams`)
  - PR counts by status (pending, in-progress, backported, skipped)
  - List of pending PRs with titles and confidence
  - In-progress PRs
  - Open downstream PRs awaiting merge
  - PRs needing propagation
- Empty target groups show `(no entries)` — so `start-teams` is visible even when empty

This is a read-only operation — no lock needed.

$ARGUMENTS
