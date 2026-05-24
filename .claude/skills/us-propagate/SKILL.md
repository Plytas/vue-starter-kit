---
name: us-propagate
description: Propagate a backported PR to a downstream project repo
---

Load the upstream-sync skill and execute the **propagate** subcommand.

Usage: `/us-propagate <entry-id> [downstream-repo-id] [--target=BRANCH]`

Default: `--target=start`.

For the specified backported entry, intelligently apply the change to a downstream repo:

1. Read the backported diff from the fork PR
2. Identify eligible downstream repos: only repos where `downstreamRepos[*].tracksBranch === target`
3. If no `downstream-repo-id` specified, list eligible repos and let me select
4. Analyze the downstream repo's structure and divergence
5. Adapt the change to the downstream repo's patterns
6. Create branch, commit, and open PR via `gh pr create --repo <owner>/<downstream-repo>` with:
   - Reference to upstream PR and fork PR
   - Adaptation notes
   - **Output the PR URL to the user immediately after creation**
7. Update state:
   ```bash
   php .starter-kit/scripts/sync.php downstream-set <entry-id> <repo> \
     --target=<target> --status=open --number=<N> --url=<url>
   ```

**Important:** Only `downstreamRepos[*]` with `tracksBranch === target` are valid propagation targets. A repo tracking `start` cannot receive a `start-teams` backport and vice versa.

Arguments: $ARGUMENTS
