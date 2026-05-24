---
name: us-backport
description: Backport upstream PRs to this fork with intelligent adaptation
---

Load the upstream-sync skill and execute the **backport** subcommand for PR numbers: $ARGUMENTS

Usage: `/us-backport <pr-numbers> [--target=start|start-teams]`

Default: `--target=start`.

For each PR number provided:

1. Read `.starter-kit/adaptation-guide.md` to understand fork patterns
2. Fetch the upstream diff and understand its intent:
   ```bash
   gh pr diff <number> --repo laravel/vue-starter-kit
   gh pr view <number> --repo laravel/vue-starter-kit --json title,body,mergedAt
   ```
3. Acquire lock (operation: "backport")
4. Set status to `in-progress`:
   ```bash
   php .starter-kit/scripts/sync.php pr-set <number> --target=<target> --status=in-progress
   ```
5. Ask me whether to apply as-is or adapt to fork patterns
6. Create branch **`backport/<target>/upstream-pr-<number>`** from the appropriate base branch
7. Apply changes and commit
8. Run validation:
   ```bash
   php artisan typescript:transform
   ./vendor/bin/phpstan analyse --memory-limit=2G
   bunx vue-tsc --noEmit
   ```
9. Create PR via `gh pr create` (defaults to fork) with:
   - Link to upstream PR: `Backports laravel/vue-starter-kit#<number>`
   - Target branch: `start` (or `start-teams` if `--target=start-teams`)
   - Confidence level and adaptation summary
   - **Output the PR URL to the user immediately**
10. Update state:
    ```bash
    php .starter-kit/scripts/sync.php pr-set <number> --target=<target> --status=backported \
      --fork-pr=<url> --confidence=<level> --notes="..."
    ```
11. Release lock

If no PR numbers are provided, read `.starter-kit/upstream-sync.json` and show pending entries for the target branch.
