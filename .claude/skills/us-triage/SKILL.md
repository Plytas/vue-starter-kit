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
4. Determine `lastCheckedAt` and `lastCheckedSha` from `upstreamWatermarks[<upstream-branch>]`.
5. Enumerate upstream activity since the watermark via **both** paths — PR-linked work AND direct-to-main pushes:
   ```bash
   # Path A — merged PRs (historical):
   gh pr list --repo laravel/vue-starter-kit --search "is:merged merged:>={lastCheckedAt}" --limit 200 --json number,title,body,mergedAt,labels

   # Path B — ALL commits in the watermark gap (catches direct pushes from push-kit-changes.yml etc.):
   git log --no-merges --format='%H%x09%s' {lastCheckedSha}..upstream/{upstream-branch}
   ```
   Path B is required: upstream has shifted toward direct-to-`main` pushes that Path A misses entirely. Every SHA in Path B that isn't already attributable to a PR from Path A becomes a `commit:<sha>` triage candidate.
   (For `--upstream-branch=teams`, fetch from the `teams` branch / equivalent.)
6. Filter out entries already in the state file (match by PR number AND by short SHA — `commit:<sha>` entries may already exist for some Path B commits).
7. For each new PR or commit, fetch the diff and classify (recommended / skip / already-covered / high-risk). For `already-covered` skips, **cite the fork chunk / commit that absorbed it** in the reason field — uncited "already-covered" skips have historically been impossible to re-validate.
8. Present interactive summary; let user select: **backport** / **skip** / **defer**.
9. Update state file:
   - Add new entries with:
     - `sourceRefs.<upstream-branch>` set (commit SHA for direct-push entries, PR ref for PR entries)
     - `targets.start = { status: pending }` (or `targets.start-teams = { status: pending }` if triaged off `upstream/teams`)
   - **Do not advance the watermark until every Path B SHA has a definitive ledger status.** Touching the watermark with untriaged SHAs in the gap silently buries them.
   - Update `upstreamWatermarks[<upstream-branch>]` (both `lastCheckedAt` and `lastCheckedSha`) via:
     ```bash
     php .starter-kit/scripts/sync.php touch-checked --upstream-branch=<name> --sha=$(git rev-parse upstream/<branch>)
     ```
10. Release lock.
11. Show summary: "X new PRs found, Y marked for backport, Z skipped, W deferred."

**Note:** New entries triaged off `upstream/main` get `targets.start`; entries triaged off `upstream/teams` get `targets.start-teams`. The `sourceRefs` key matches the upstream branch name.

$ARGUMENTS
