#!/usr/bin/env php
<?php

/**
 * Upstream sync state-machine CLI.
 *
 * Single entry point for managing .starter-kit/upstream-sync.json.
 * Drives the /us-next workflow: triage -> backport -> propagate -> close.
 *
 * Subcommands:
 *   next [--execute]              Resolve and print the next action; with --execute
 *                                 perform automatic steps (poll GitHub for merges).
 *   status                        High-level summary (counts, queue, last triage).
 *   poll-downstream [--pr=N]      Query GitHub for each open downstream PR and update
 *                                 status if merged. Defaults to all backported PRs.
 *   downstream-set <pr> <repo>... Mark downstream PR(s). Flags: --status=open|merged|skipped
 *                                 --number=N --url=URL --reason=TEXT
 *   pr-set <pr>                   Update an existing PR entry. Flags: --status=...
 *                                 --fork-pr=URL --confidence=high|medium|low
 *                                 --notes=TEXT --reason=TEXT
 *   touch-checked                 Set lastCheckedAt to now.
 *   lock acquire|release          Manage .starter-kit/upstream-sync.lock.
 *                                 acquire flags: --operation=NAME [--force]
 *
 * Exit codes:
 *   0 success, 1 user error, 2 state error, 3 lock conflict.
 */

const STATE_FILE = __DIR__ . '/../upstream-sync.json';
const LOCK_FILE = __DIR__ . '/../upstream-sync.lock';
const STALE_LOCK_MINUTES = 30;

main($argv);

function main(array $argv): void
{
    $cmd = $argv[1] ?? null;
    $rest = array_slice($argv, 2);
    [$args, $flags] = parseArgs($rest);

    match ($cmd) {
        'next' => cmdNext($flags),
        'status' => cmdStatus(),
        'poll-downstream' => cmdPollDownstream($flags),
        'downstream-set' => cmdDownstreamSet($args, $flags),
        'pr-set' => cmdPrSet($args, $flags),
        'touch-checked' => cmdTouchChecked(),
        'lock' => cmdLock($args, $flags),
        '--help', '-h', null => printUsage(),
        default => fail("Unknown command: {$cmd}. Run with --help.", 1),
    };
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------

function cmdNext(array $flags): void
{
    $state = loadState();
    $execute = isset($flags['execute']);
    $action = resolveNext($state, $execute);

    echo $action['summary'] . "\n";
    if (!empty($action['detail'])) {
        echo "\n" . $action['detail'] . "\n";
    }
    if (!empty($action['hint'])) {
        echo "\nNext step: " . $action['hint'] . "\n";
    }
}

function cmdStatus(): void
{
    $state = loadState();
    $counts = ['pending' => 0, 'in-progress' => 0, 'backported' => 0, 'skipped' => 0];
    $pendingList = [];
    $inProgressList = [];
    $awaitingMerge = [];      // backported but fork PR open
    $awaitingPropagate = [];  // backported, fork PR merged, downstream missing
    $awaitingDownstream = []; // open downstream PRs

    foreach ($state['prs'] as $num => $pr) {
        $status = $pr['status'] ?? 'pending';
        $counts[$status] = ($counts[$status] ?? 0) + 1;

        if ($status === 'pending') {
            $pendingList[$num] = $pr;
        } elseif ($status === 'in-progress') {
            $inProgressList[$num] = $pr;
        } elseif ($status === 'backported') {
            $opens = openDownstreamPrs($pr);
            if ($opens) {
                $awaitingDownstream[$num] = $opens;
            }
            $missing = missingDownstreamRepos($state, $pr);
            if ($missing) {
                $awaitingPropagate[$num] = $missing;
            }
        }
    }

    $last = $state['lastCheckedAt'] ?? null;
    $lastAgo = $last ? humanAgo($last) : 'never';

    echo "Upstream sync — {$state['upstream']}\n";
    echo "Last triaged:  {$lastAgo}" . ($last ? " ({$last})" : '') . "\n";
    echo str_repeat('=', 70) . "\n";
    printf("Counts: pending=%d, in-progress=%d, backported=%d, skipped=%d\n",
        $counts['pending'], $counts['in-progress'], $counts['backported'], $counts['skipped']);

    if ($pendingList) {
        ksort($pendingList, SORT_NUMERIC);
        echo "\nPending backports (" . count($pendingList) . "):\n";
        foreach ($pendingList as $n => $p) {
            $conf = !empty($p['confidence']) ? " [{$p['confidence']}]" : '';
            echo "  #{$n}{$conf}  {$p['title']}\n";
        }
    }

    if ($inProgressList) {
        echo "\nIn progress (" . count($inProgressList) . "):\n";
        foreach ($inProgressList as $n => $p) {
            echo "  #{$n}  {$p['title']}\n";
        }
    }

    if ($awaitingDownstream) {
        echo "\nOpen downstream PRs:\n";
        foreach ($awaitingDownstream as $n => $opens) {
            $repos = array_keys($opens);
            echo "  #{$n}: " . implode(', ', $repos) . "\n";
        }
    }

    if ($awaitingPropagate) {
        echo "\nNeeds propagation:\n";
        foreach ($awaitingPropagate as $n => $repos) {
            echo "  #{$n}: " . implode(', ', $repos) . "\n";
        }
    }

    echo "\n";
}

function cmdPollDownstream(array $flags): void
{
    $state = loadState();
    $only = $flags['pr'] ?? null;
    $checked = 0;
    $updated = 0;

    foreach ($state['prs'] as $num => &$pr) {
        if (($pr['status'] ?? null) !== 'backported') continue;
        if ($only && (string)$num !== (string)$only) continue;
        if (empty($pr['downstreamPrs'])) continue;

        foreach ($pr['downstreamPrs'] as $repoId => &$dp) {
            if (($dp['status'] ?? null) !== 'open') continue;
            if (empty($dp['url'])) continue;
            $checked++;

            $merged = ghIsPrMerged($dp['url']);
            if ($merged === null) {
                fwrite(STDERR, "  ! could not check {$dp['url']}\n");
                continue;
            }
            if ($merged) {
                $dp['status'] = 'merged';
                $dp['mergedAt'] = date('c');
                $updated++;
                echo "  ✓ #{$num} {$repoId}: merged ({$dp['url']})\n";
            }
        }
        unset($dp);
    }
    unset($pr);

    if ($updated > 0) {
        saveState($state);
    }

    echo "\nChecked {$checked} open downstream PR(s); updated {$updated}.\n";
}

function cmdDownstreamSet(array $args, array $flags): void
{
    if (count($args) < 2) {
        fail("Usage: downstream-set <pr> <repo> [<repo>...] --status=STATUS [--number=N] [--url=URL] [--reason=TEXT]", 1);
    }
    $prNum = $args[0];
    $repos = array_slice($args, 1);
    $status = $flags['status'] ?? 'merged';

    if (!in_array($status, ['open', 'merged', 'skipped'], true)) {
        fail("Invalid --status: {$status}", 1);
    }

    $state = loadState();
    if (!isset($state['prs'][$prNum])) {
        fail("PR #{$prNum} not found in state", 2);
    }

    $now = date('c');
    foreach ($repos as $repo) {
        if (!validDownstreamRepo($state, $repo)) {
            fail("Unknown downstream repo: {$repo}", 2);
        }
        $existing = $state['prs'][$prNum]['downstreamPrs'][$repo] ?? [];
        $entry = array_merge($existing, ['status' => $status]);

        if ($status === 'skipped') {
            $entry['reason'] = $flags['reason'] ?? ($existing['reason'] ?? 'skipped');
            unset($entry['number'], $entry['url'], $entry['mergedAt']);
        } else {
            if (isset($flags['number'])) $entry['number'] = (int)$flags['number'];
            if (isset($flags['url'])) $entry['url'] = $flags['url'];
            if (!isset($entry['propagatedAt'])) $entry['propagatedAt'] = $now;
            if ($status === 'merged' && !isset($entry['mergedAt'])) {
                $entry['mergedAt'] = $now;
            }
        }

        $state['prs'][$prNum]['downstreamPrs'][$repo] = $entry;
        echo "  ✓ #{$prNum} {$repo}: {$status}\n";
    }

    saveState($state);
}

function cmdPrSet(array $args, array $flags): void
{
    if (count($args) < 1) {
        fail("Usage: pr-set <pr> [--status=...] [--fork-pr=URL] [--confidence=...] [--notes=TEXT] [--reason=TEXT]", 1);
    }
    $prNum = $args[0];
    $state = loadState();
    if (!isset($state['prs'][$prNum])) {
        fail("PR #{$prNum} not found in state", 2);
    }
    $pr = &$state['prs'][$prNum];

    if (isset($flags['status'])) {
        if (!in_array($flags['status'], ['pending', 'in-progress', 'backported', 'skipped'], true)) {
            fail("Invalid --status: {$flags['status']}", 1);
        }
        $pr['status'] = $flags['status'];
        if ($flags['status'] === 'backported' && empty($pr['backportedAt'])) {
            $pr['backportedAt'] = date('c');
        }
    }
    if (isset($flags['fork-pr'])) $pr['forkPR'] = $flags['fork-pr'];
    if (isset($flags['confidence'])) $pr['confidence'] = $flags['confidence'];
    if (isset($flags['notes'])) $pr['adaptationNotes'] = $flags['notes'];
    if (isset($flags['reason'])) $pr['reason'] = $flags['reason'];

    saveState($state);
    echo "  ✓ #{$prNum} updated\n";
}

function cmdTouchChecked(): void
{
    $state = loadState();
    $state['lastCheckedAt'] = date('c');
    saveState($state);
    echo "lastCheckedAt = {$state['lastCheckedAt']}\n";
}

function cmdLock(array $args, array $flags): void
{
    $action = $args[0] ?? null;
    if ($action === 'acquire') {
        if (file_exists(LOCK_FILE) && empty($flags['force'])) {
            $existing = json_decode(file_get_contents(LOCK_FILE), true) ?: [];
            $age = time() - strtotime($existing['startedAt'] ?? 'now');
            if ($age < STALE_LOCK_MINUTES * 60) {
                $mins = (int)($age / 60);
                fail("Lock held by '{$existing['operation']}' for {$mins}m. Use --force to override.", 3);
            }
        }
        file_put_contents(LOCK_FILE, json_encode([
            'pid' => $flags['pid'] ?? gethostname() . ':' . getmypid(),
            'startedAt' => date('c'),
            'operation' => $flags['operation'] ?? 'unknown',
        ], JSON_PRETTY_PRINT) . "\n");
        echo "Lock acquired ({$flags['operation']}).\n";
    } elseif ($action === 'release') {
        if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
        echo "Lock released.\n";
    } else {
        fail("Usage: lock acquire|release [--operation=NAME] [--force]", 1);
    }
}

// ---------------------------------------------------------------------------
// Resolver — the heart of /us-next
// ---------------------------------------------------------------------------

/**
 * Walk the state and pick the highest-priority next action.
 *
 * Order:
 *  1. Auto-update any merged downstream PRs (only if --execute).
 *  2. Propagate: backported PRs whose fork PR is merged and missing downstream coverage.
 *  3. Wait: backported PRs whose fork PR is still open OR whose downstream PRs are open.
 *  4. Backport: lowest-numbered pending PR.
 *  5. Triage: when the queue is empty.
 */
function resolveNext(array &$state, bool $execute): array
{
    // 1. Auto-poll GitHub when --execute is on.
    if ($execute) {
        $polled = pollAndUpdateDownstreams($state);
        if ($polled['updated'] > 0) {
            saveState($state);
            return [
                'summary' => "Updated {$polled['updated']} downstream PR(s) to merged.",
                'detail' => $polled['log'],
                'hint' => 'Run /us-next again to continue.',
            ];
        }
    }

    // 2. Propagation needed.
    foreach ($state['prs'] as $num => $pr) {
        if (($pr['status'] ?? null) !== 'backported') continue;
        $missing = missingDownstreamRepos($state, $pr);
        if (!$missing) continue;

        // Check fork PR is merged before propagating.
        if (!empty($pr['forkPR'])) {
            $forkMerged = ghIsPrMerged($pr['forkPR']);
            if ($forkMerged === false) {
                continue; // fork PR not merged yet — skip, will fall to "wait"
            }
        }

        return [
            'summary' => "Propagate #{$num} to: " . implode(', ', $missing),
            'detail' => "Fork PR: " . ($pr['forkPR'] ?? '(none)') . "\nTitle: {$pr['title']}",
            'hint' => "/us-propagate {$num} " . implode(' ', $missing),
        ];
    }

    // 3. Anything waiting (open fork PR or open downstream PRs)?
    $waiting = [];
    foreach ($state['prs'] as $num => $pr) {
        if (($pr['status'] ?? null) !== 'backported') continue;
        $opens = openDownstreamPrs($pr);
        if ($opens) {
            $waiting[] = "#{$num}: open downstream PRs in " . implode(', ', array_keys($opens));
        }
    }

    // 4. Pending backports.
    $pending = array_filter($state['prs'], fn($p) => ($p['status'] ?? null) === 'pending');
    if ($pending) {
        ksort($pending, SORT_NUMERIC);
        $num = array_key_first($pending);
        $pr = $pending[$num];
        $extra = '';
        if (!empty($pr['adaptationNotes'])) $extra .= "\nNotes: {$pr['adaptationNotes']}";
        if (!empty($pr['confidence'])) $extra .= "\nConfidence: {$pr['confidence']}";
        $waitNote = $waiting ? "\n\nMeanwhile, awaiting:\n  " . implode("\n  ", $waiting) : '';

        return [
            'summary' => "Backport #{$num}: {$pr['title']}",
            'detail' => "URL: {$pr['url']}{$extra}{$waitNote}",
            'hint' => "/us-backport {$num}",
        ];
    }

    if ($waiting) {
        return [
            'summary' => "Awaiting downstream merges (" . count($waiting) . "):",
            'detail' => "  " . implode("\n  ", $waiting),
            'hint' => 'Run /us-next --execute to poll GitHub, or wait and re-check.',
        ];
    }

    // 5. Nothing in the queue — triage.
    $last = $state['lastCheckedAt'] ?? null;
    return [
        'summary' => 'Queue empty — time to triage upstream.',
        'detail' => 'Last triage: ' . ($last ? humanAgo($last) . " ({$last})" : 'never'),
        'hint' => '/us-triage',
    ];
}

function pollAndUpdateDownstreams(array &$state): array
{
    $log = [];
    $updated = 0;
    foreach ($state['prs'] as $num => &$pr) {
        if (($pr['status'] ?? null) !== 'backported') continue;
        if (empty($pr['downstreamPrs'])) continue;

        foreach ($pr['downstreamPrs'] as $repoId => &$dp) {
            if (($dp['status'] ?? null) !== 'open') continue;
            if (empty($dp['url'])) continue;

            $merged = ghIsPrMerged($dp['url']);
            if ($merged === true) {
                $dp['status'] = 'merged';
                $dp['mergedAt'] = date('c');
                $log[] = "  #{$num} {$repoId}: merged";
                $updated++;
            }
        }
        unset($dp);
    }
    unset($pr);
    return ['updated' => $updated, 'log' => implode("\n", $log)];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function loadState(): array
{
    if (!file_exists(STATE_FILE)) fail("State file not found: " . STATE_FILE, 2);
    $state = json_decode(file_get_contents(STATE_FILE), true);
    if (!is_array($state)) fail("State file is invalid JSON.", 2);
    return $state;
}

function saveState(array $state): void
{
    $tmp = STATE_FILE . '.tmp';
    file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    rename($tmp, STATE_FILE);
}

function openDownstreamPrs(array $pr): array
{
    $opens = [];
    foreach ($pr['downstreamPrs'] ?? [] as $repo => $dp) {
        if (($dp['status'] ?? null) === 'open') $opens[$repo] = $dp;
    }
    return $opens;
}

function missingDownstreamRepos(array $state, array $pr): array
{
    $covered = array_keys($pr['downstreamPrs'] ?? []);
    $all = array_map(fn($r) => $r['id'], $state['downstreamRepos'] ?? []);
    $active = array_filter($state['downstreamRepos'] ?? [], fn($r) => ($r['status'] ?? 'active') === 'active');
    $activeIds = array_map(fn($r) => $r['id'], $active);
    return array_values(array_diff($activeIds, $covered));
}

function validDownstreamRepo(array $state, string $repoId): bool
{
    foreach ($state['downstreamRepos'] ?? [] as $r) {
        if ($r['id'] === $repoId) return true;
    }
    return false;
}

/**
 * Returns true if merged, false if open, null if unknown/error.
 */
function ghIsPrMerged(string $url): ?bool
{
    if (!preg_match('#github\.com/([^/]+)/([^/]+)/pull/(\d+)#', $url, $m)) {
        return null;
    }
    [$_, $owner, $repo, $num] = $m;
    $repoArg = escapeshellarg("{$owner}/{$repo}");
    $cmd = "gh pr view {$num} --repo {$repoArg} --json state 2>/dev/null";
    $out = shell_exec($cmd);
    if (!$out) return null;
    $data = json_decode($out, true);
    $state = $data['state'] ?? null;
    if ($state === 'MERGED') return true;
    if (in_array($state, ['OPEN', 'CLOSED'], true)) return $state === 'OPEN' ? false : null;
    return null;
}

function humanAgo(string $iso): string
{
    $diff = time() - strtotime($iso);
    if ($diff < 60) return "{$diff}s ago";
    if ($diff < 3600) return (int)($diff / 60) . 'm ago';
    if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
    return (int)($diff / 86400) . 'd ago';
}

function parseArgs(array $rest): array
{
    $args = [];
    $flags = [];
    foreach ($rest as $a) {
        if (str_starts_with($a, '--')) {
            $kv = substr($a, 2);
            if (str_contains($kv, '=')) {
                [$k, $v] = explode('=', $kv, 2);
                $flags[$k] = $v;
            } else {
                $flags[$kv] = true;
            }
        } else {
            $args[] = $a;
        }
    }
    return [$args, $flags];
}

function fail(string $msg, int $code): never
{
    fwrite(STDERR, "Error: {$msg}\n");
    exit($code);
}

function printUsage(): void
{
    $self = basename(__FILE__);
    echo <<<TXT
Usage: php {$self} <command> [args] [--flags]

Commands:
  next [--execute]                Resolve and print the next workflow action.
  status                          Summary of state file.
  poll-downstream [--pr=N]        Update merged-on-GitHub downstream PRs.
  downstream-set <pr> <repo>...   Mark downstream PR. Flags:
                                    --status=open|merged|skipped
                                    --number=N --url=URL --reason=TEXT
  pr-set <pr>                     Update a PR entry. Flags:
                                    --status=... --fork-pr=URL
                                    --confidence=high|medium|low
                                    --notes=TEXT --reason=TEXT
  touch-checked                   Set lastCheckedAt = now.
  lock acquire|release            Manage lock file. acquire flags:
                                    --operation=NAME [--force]

State file: .starter-kit/upstream-sync.json
TXT;
    echo "\n";
}
