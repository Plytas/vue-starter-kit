# Upstream Sync Scripts

This directory contains PHP scripts for managing the upstream sync state (schema v2).

## Primary entry point: `sync.php`

`sync.php` is the unified CLI for all state mutations and the `/us-next` resolver.

```bash
php sync.php --help   # Full command reference with defaults
```

### Command surface (schema v2)

| Command | Key flags | Defaults |
|---|---|---|
| `migrate [--dry-run]` | — | — |
| `next [--execute]` | `[--target=start\|start-teams\|all]` | `--target=all` |
| `status` | `[--target=start\|start-teams\|all]` | `--target=all` |
| `poll-downstream` | `[--pr=ID] [--target=...]` | `--target=all` |
| `pr-set <entry>` | `[--target=BRANCH] [--status=...] [--fork-pr=URL] [--confidence=...] [--notes=...] [--reason=...]` | `--target=start` |
| `downstream-set <entry> <repo>...` | `[--target=BRANCH] --status=... [--number=N] [--url=URL] [--reason=...]` | `--target=start` |
| `touch-checked` | `[--upstream-branch=main\|teams] [--sha=SHA]` | `--upstream-branch=main` |
| `lock acquire\|release` | `--operation=NAME [--force]` | — |

**Flag concepts:**
- `--target=BRANCH` — fork-side branch scope (`start`, `start-teams`, or `all` on query commands).
  Mutating commands (`pr-set`, `downstream-set`) reject `all`.
- `--upstream-branch=NAME` — upstream tracking branch (`main` or `teams`).
  Only `touch-checked` accepts this flag.

**Env override:** `SYNC_STATE_FILE=/path/to/file.json php sync.php <cmd>` uses that file instead of `upstream-sync.json`. Useful for smoke testing without touching production state.

### `next` resolver (drives `/us-next`)

Walks `upstream-sync.json` and picks the highest-priority action:

1. With `--execute`: auto-marks any merged downstream PRs (GitHub poll).
2. Propagate any backported entry whose fork PR is merged but downstream coverage is missing.
3. Report entries awaiting fork-PR or downstream-PR merges.
4. Backport the lowest-numbered pending entry (`start` before `start-teams`).
5. Triage the upstream branch with the oldest `lastCheckedAt` watermark.

Output ends with a `Next step:` line naming the slash command to run.

---

## Schema v2 structure

```json
{
  "version": 2,
  "upstream": "laravel/vue-starter-kit",
  "upstreamWatermarks": {
    "main":  { "lastCheckedAt": "2026-05-18T00:00:00Z", "lastCheckedSha": null },
    "teams": { "lastCheckedAt": null,                   "lastCheckedSha": null }
  },
  "downstreamRepos": [
    { "id": "idle-rpg", "path": "/path", "status": "active", "tracksBranch": "start" }
  ],
  "prs": {
    "158": {
      "title": "...",
      "url": "https://github.com/laravel/vue-starter-kit/pull/158",
      "sourceRefs": {
        "main":  { "type": "pr",     "id": "158",    "url": "...", "mergedAt": "..." },
        "teams": { "type": "commit", "id": "abc123", "url": "...", "mergedAt": null }
      },
      "targets": {
        "start": {
          "status": "backported",
          "forkPR": "https://github.com/Plytas/vue-starter-kit/pull/1",
          "backportedAt": "...",
          "confidence": "high",
          "adaptationNotes": "...",
          "downstreamPrs": {
            "idle-rpg": { "number": 1, "status": "merged", "url": "...", "propagatedAt": "...", "mergedAt": "..." }
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

**Key invariants enforced by `validateState`:**
- `downstreamRepos[*].tracksBranch ∈ {start, start-teams}`
- `prs[*].targets[*].status ∈ {pending, in-progress, backported, skipped}`
- `prs[*].sourceRefs` keys ⊆ `{main, teams}`; at least one required
- `upstreamWatermarks` keys ⊆ `{main, teams}`; `main` required
- No legacy v1 fields (`status`, `forkPr`, `downstreamPrs`, `downstreams`, etc.) at top level of each entry

---

## Legacy single-purpose scripts

These scripts wrap `sync.php` functionality and require **schema v2**.

### `add-downstream-repo`

Add a new downstream repository to track.

```bash
./add-downstream-repo <repo-id> <path> [--target=start|start-teams]
```

Example:
```bash
./add-downstream-repo my-project /Users/me/Code/my-project
./add-downstream-repo teams-app /Users/me/Code/teams-app --target=start-teams
```

Sets `tracksBranch` from `--target` (default: `start`).

---

### `record-propagation`

Record that a backported entry has been propagated to a downstream repository.

```bash
./record-propagation <entry-id> <downstream-repo-id> <downstream-pr-number> [status] [--target=BRANCH]
```

Example:
```bash
./record-propagation 158 my-app 42 open
./record-propagation 158 teams-app 7 open --target=start-teams
```

Validates that `downstreamRepos[repo].tracksBranch === target`. Writes into `prs[entry].targets[target].downstreamPrs[repo]`.

---

### `list-downstream-repos`

Display all downstream repositories and their propagation status.

```bash
./list-downstream-repos [--target=start|start-teams|all]
```

Shows `tracksBranch`, propagation counts, and open PR links.

---

### `latest-backport` / `next-backport` (deprecated)

These scripts are **fail-fast stubs** in schema v2. Use instead:

```bash
php sync.php status --target=start   # replaces latest-backport
php sync.php next --target=start     # replaces next-backport
```

---

## Workflow Example

1. **Backport an upstream PR to the fork** (using `/us-backport`)

2. **Propagate to downstream:**
   - Apply the change in the downstream repo, open a PR
   - Record the propagation:
     ```bash
     ./record-propagation 158 my-app 42 open
     # or via sync.php:
     php sync.php downstream-set 158 my-app --status=open --number=42
     ```

3. **Add a new downstream repo:**
   ```bash
   ./add-downstream-repo my-app /path/to/my-app
   # for a teams-variant project:
   ./add-downstream-repo teams-app /path/to/teams-app --target=start-teams
   ```

4. **View status:**
   ```bash
   php sync.php status
   ./list-downstream-repos
   ```

5. **Update when PR is merged:**
   ```bash
   php sync.php downstream-set 158 my-app --status=merged
   ```
