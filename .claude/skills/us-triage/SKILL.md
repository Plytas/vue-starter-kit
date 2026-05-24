---
name: us-triage
description: Check for new upstream PRs and triage them for backporting
---

Load the upstream-sync skill and execute the **triage** subcommand.

Usage: `/us-triage [--upstream-branch=main|teams]`

Default: `--upstream-branch=main`.

Fetch new merged PRs (or commits) from the upstream `laravel/vue-starter-kit` repo on the specified branch, classify them (recommended / skip / already-covered / high-risk), and let me select which to backport, skip, or defer.

## Steps

1. Read `.starter-kit/upstream-sync.json` for current state and `.starter-kit/adaptation-guide.md` for fork context.
2. Acquire lock (operation: "triage").
3. Run `git fetch upstream`.
4. Determine `lastCheckedAt` from `upstreamWatermarks[<upstream-branch>].lastCheckedAt`.
5. Fetch merged PRs since that timestamp:
   ```bash
   gh pr list --repo laravel/vue-starter-kit --search "is:merged merged:>={lastCheckedAt}" --limit 200 --json number,title,body,mergedAt,labels
   ```
   (For `--upstream-branch=teams`, fetch from the `teams` branch / equivalent.)
6. Filter out entries already in the state file.
7. For each new PR/commit, fetch the diff and classify (recommended / skip / already-covered / high-risk).
8. Present interactive summary; let user select: **backport** / **skip** / **defer**.
9. Update state file:
   - Add new PR entries with:
     - `sourceRefs.<upstream-branch>` set
     - `targets.start = { status: pending }` (or `targets.start-teams = { status: pending }` if triaged off `upstream/teams`)
   - Update `upstreamWatermarks[<upstream-branch>].lastCheckedAt` via:
     ```bash
     php .starter-kit/scripts/sync.php touch-checked --upstream-branch=<name>
     ```
10. Release lock.
11. Show summary: "X new PRs found, Y marked for backport, Z skipped, W deferred."

**Note:** New entries triaged off `upstream/main` get `targets.start`; entries triaged off `upstream/teams` get `targets.start-teams`. The `sourceRefs` key matches the upstream branch name.

$ARGUMENTS
