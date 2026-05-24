---
name: upstream-sync
description: Track and backport merged PRs from the upstream Laravel vue-starter-kit repo into this fork, with intelligent adaptation to fork patterns (spatie/laravel-data, etc.) and downstream propagation to projects built from this starter kit.
---

# Upstream Sync Agent

You help keep this fork of `laravel/vue-starter-kit` in sync with upstream by tracking merged PRs, intelligently backporting changes, and propagating them to downstream projects.

## Important Files

- **State file**: `.starter-kit/upstream-sync.json` — schema v2; tracks PR state per fork branch, downstream repos, and upstream watermarks
- **State CLI**: `.starter-kit/scripts/sync.php` — **prefer this for any state mutation.** Run `php .starter-kit/scripts/sync.php --help` for the full reference.
- **Adaptation guide**: `.starter-kit/adaptation-guide.md` — maps upstream patterns to fork conventions. You MUST read this before adapting any change.
- **Fork Data classes**: `app/Data/` — this fork uses `spatie/laravel-data` instead of FormRequests
- **Generated types**: `resources/js/types/generated.d.ts` — auto-generated from Data classes via `spatie/laravel-typescript-transformer`

## State mutations: use `sync.php`, not manual JSON edits

**Never read/edit `upstream-sync.json` directly when a `sync.php` subcommand exists for the operation.** Examples:

| Operation | Command |
|-----------|---------|
| Mark PR backported with fork PR + notes | `php .starter-kit/scripts/sync.php pr-set 250 --target=start --status=backported --fork-pr=https://... --confidence=high --notes="..."` |
| Mark PR skipped | `php .starter-kit/scripts/sync.php pr-set 200 --target=start --status=skipped --reason="cosmetic"` |
| Mark downstream PRs merged | `php .starter-kit/scripts/sync.php downstream-set 185 idle-rpg joy katsch katsch-gw2 --target=start --status=merged` |
| Record a propagation | `php .starter-kit/scripts/sync.php downstream-set 196 idle-rpg --target=start --status=open --number=12 --url=https://...` |
| Skip a downstream repo for a PR | `php .starter-kit/scripts/sync.php downstream-set 196 joymobile --target=start --status=skipped --reason="no NavMain"` |
| Auto-poll merged downstream PRs | `php .starter-kit/scripts/sync.php poll-downstream` |
| Refresh watermark after triage | `php .starter-kit/scripts/sync.php touch-checked --upstream-branch=main` |
| Acquire/release lock | `php .starter-kit/scripts/sync.php lock acquire --operation=backport` / `lock release` |

**Default `--target` for mutating commands is `start`.** Always pass `--target` explicitly so intent is clear.

Direct JSON editing is reserved for fields the CLI does not yet cover (e.g. introducing a brand-new PR entry during triage, adding a `sourceRefs.teams` slot).

## ⚠️ Repository Configuration

**The repository has been configured with `gh repo set-default Plytas/vue-starter-kit`**

This means:
- **Fork PRs**: Can use `gh pr create` (defaults to fork) OR be explicit with `--repo Plytas/vue-starter-kit`
- **Downstream PRs**: MUST use `gh pr create --repo <org>/<repo>` with the specific downstream repo
- **Upstream PRs**: Would need `--repo laravel/vue-starter-kit` (should never happen in normal workflow)

## General Guidelines

- **Always output PR URLs**: Whenever you create a PR (fork or downstream), immediately output the PR URL to the user so they can access it directly.

## State File Schema (v2)

```json
{
  "version": 2,
  "upstream": "laravel/vue-starter-kit",
  "upstreamWatermarks": {
    "main":  { "lastCheckedAt": "ISO-8601 timestamp", "lastCheckedSha": "abc123" },
    "teams": { "lastCheckedAt": null, "lastCheckedSha": null }
  },
  "downstreamRepos": [
    {
      "id": "idle-rpg",
      "path": "/absolute/path/to/repo",
      "status": "active",
      "tracksBranch": "start"
    }
  ],
  "prs": {
    "<entry-id>": {
      "title": "PR title",
      "url": "https://github.com/laravel/vue-starter-kit/pull/N",
      "sourceRefs": {
        "main":  { "type": "pr",     "id": "N",      "url": "...", "mergedAt": "..." },
        "teams": { "type": "commit", "id": "abc123", "url": "...", "mergedAt": null }
      },
      "targets": {
        "start": {
          "status": "pending | in-progress | backported | skipped",
          "forkPR": "https://github.com/Plytas/vue-starter-kit/pull/N",
          "backportedAt": "ISO-8601 timestamp",
          "confidence": "high | medium | low",
          "adaptationNotes": "What was changed and why",
          "reason": "Why it was skipped (required if skipped)",
          "downstreamPrs": {
            "idle-rpg": { "number": 17, "status": "open | merged | skipped", "url": "...", "propagatedAt": "...", "mergedAt": "..." }
          }
        },
        "start-teams": {
          "status": "pending"
        }
      }
    }
  }
}
```

**Key invariants (enforced by `validateState`):**
- `downstreamRepos[*].tracksBranch ∈ {start, start-teams}`
- `prs[*].targets[*].status ∈ {pending, in-progress, backported, skipped}`
- `prs[*].sourceRefs` keys ⊆ `{main, teams}`; at least one required
- `upstreamWatermarks` keys ⊆ `{main, teams}`; `main` key required

## Multi-branch fork

This fork tracks two upstream branches:

| Fork branch   | Tracks upstream | Status           |
|---|---|---|
| `start`       | `upstream/main` | Active (154 entries) |
| `start-teams` | `upstream/teams`| Seeded in Chunk 8b  |

- `--target=BRANCH` selects which fork branch to operate on. Defaults to `start` for mutating commands, `all` for query commands.
- `--upstream-branch=main|teams` is only used by `touch-checked` (watermarks).

## Lockfile

Before any mutating operation, check for `.starter-kit/upstream-sync.lock`. If it exists and is less than 30 minutes old, warn the user. If older, offer to override. Create the lock at the start and delete it when done.

## Subcommand: triage

Invoked via `/us-triage` (or when the user asks to check for upstream changes).

See `/us-triage` skill for detailed steps.

## Subcommand: backport

Invoked via `/us-backport <pr-numbers> [--target=start|start-teams]`.

See `/us-backport` skill for detailed steps.

## Subcommand: status

Invoked via `/us-status [--target=BRANCH|all]`.

See `/us-status` skill for detailed steps.

## Subcommand: mark

Invoked via `/us-mark <entry> <status> [reason] [--target=BRANCH]`.

See `/us-mark` skill for detailed steps.

## Subcommand: propagate

Invoked via `/us-propagate <entry-id> [downstream-repo-id]`.

Only consider downstream repos where `tracksBranch === target`. See `/us-propagate` skill.

## Dry Run Mode

If the user says "dry run" or "--dry-run", simulate all operations without creating branches, PRs, or modifying the state file.

## Error Handling

- If `gh` commands fail, check authentication: `gh auth status`
- If upstream remote doesn't exist: `git remote add upstream git@github.com:laravel/vue-starter-kit.git`
- If state file is corrupted, offer to reset it (after backing up)
- Always release the lock in case of errors
