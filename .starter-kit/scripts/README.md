# Upstream Sync Scripts

This directory contains executable PHP scripts for managing the upstream sync state.

## Scripts

### `add-downstream-repo`

Add a new downstream repository to track.

**Usage:**
```bash
./add-downstream-repo <repo-id> <path>
```

**Example:**
```bash
./add-downstream-repo my-project /Users/me/Code/my-project
```

**What it does:**
- Validates the path exists and is a git repository
- Adds the repository to `downstreamRepos` in `upstream-sync.json`
- Sorts repositories by ID for consistency

---

### `record-propagation`

Record that a backported PR has been propagated to a downstream repository.

**Usage:**
```bash
./record-propagation <upstream-pr-number> <downstream-repo-id> <downstream-pr-number> [status]
```

**Example:**
```bash
./record-propagation 158 my-project 42 open
```

**Status values:**
- `open` (default) - PR is open
- `merged` - PR has been merged

**What it does:**
- Validates the upstream PR and downstream repo exist in the state file
- Automatically detects the GitHub PR URL from the repo's remote
- Records the propagation in `downstreamPrs` with timestamp
- Updates the state file

---

### `list-downstream-repos`

Display all downstream repositories and their propagation status.

**Usage:**
```bash
./list-downstream-repos
```

**What it shows:**
- Repository ID, path, and status
- Total number of propagations
- Count of open vs merged downstream PRs
- List of currently open PRs with links

---

## Workflow Example

1. **Add a downstream repository:**
   ```bash
   ./add-downstream-repo my-app /path/to/my-app
   ```

2. **Backport an upstream PR to the fork** (using `/us-backport`)

3. **Propagate to downstream:**
   - Manually apply the change in the downstream repo
   - Create a PR in the downstream repo
   - Record the propagation:
     ```bash
     ./record-propagation 158 my-app 42 open
     ```

4. **View status:**
   ```bash
   ./list-downstream-repos
   ```

5. **Update when PR is merged:**
   ```bash
   ./record-propagation 158 my-app 42 merged
   ```

---

## State File Schema

The scripts manage `upstream-sync.json` which has this structure for downstream repos:

```json
{
  "downstreamRepos": [
    {
      "id": "my-app",
      "path": "/Users/me/Code/my-app",
      "status": "active"
    }
  ],
  "prs": {
    "158": {
      "downstreamPrs": {
        "my-app": {
          "number": 42,
          "status": "open",
          "propagatedAt": "2026-03-15T16:49:35+00:00",
          "url": "https://github.com/owner/my-app/pull/42"
        }
      }
    }
  }
}
```

---

## Future Enhancements

Potential improvements:
- `update-propagation-status` - Update PR status (open/merged) without re-recording
- `remove-downstream-repo` - Remove a downstream repo (with confirmation)
- `propagation-summary` - Show which PRs have been propagated to which repos
- Integration with the main upstream-sync skill for automated propagation
