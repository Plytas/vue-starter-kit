#!/usr/bin/env php
<?php

/**
 * Upstream sync state-machine CLI.
 *
 * Single entry point for managing .starter-kit/upstream-sync.json.
 * Drives the /us-next workflow: triage -> backport -> propagate -> close.
 * Supports multi-branch fork (start / start-teams).
 *
 * Run with --help for full command documentation.
 *
 * Exit codes:
 *   0 success, 1 user error, 2 state error, 3 lock conflict.
 */

const STATE_FILE                = __DIR__ . '/../upstream-sync.json';
const LOCK_FILE                 = __DIR__ . '/../upstream-sync.lock';
const STALE_LOCK_MINUTES        = 30;
const SCHEMA_VERSION            = 2;
const SUPPORTED_TARGETS         = ['start', 'start-teams'];
const SUPPORTED_UPSTREAM_BRANCHES = ['main', 'teams'];
/** Maps each fork branch to its tracking upstream branch — policy, not config. */
const TARGET_UPSTREAM_BRANCH    = ['start' => 'main', 'start-teams' => 'teams'];

main($argv);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

function stateFile(): string
{
    return getenv('SYNC_STATE_FILE') ?: STATE_FILE;
}

function lockFile(): string
{
    $sf = stateFile();
    return ($sf !== STATE_FILE) ? $sf . '.lock' : LOCK_FILE;
}

function bakFile(): string
{
    return dirname(stateFile()) . '/upstream-sync.v1.json.bak';
}

function main(array $argv): void
{
    $cmd  = $argv[1] ?? null;
    $rest = array_slice($argv, 2);
    [$args, $flags] = parseArgs($rest);

    match ($cmd) {
        'migrate'         => cmdMigrate($flags),
        'next'            => cmdNext($flags),
        'status'          => cmdStatus($flags),
        'poll-downstream' => cmdPollDownstream($flags),
        'downstream-set'  => cmdDownstreamSet($args, $flags),
        'pr-set'          => cmdPrSet($args, $flags),
        'touch-checked'   => cmdTouchChecked($flags),
        'lock'            => cmdLock($args, $flags),
        '--help', '-h', null => printUsage(),
        default           => fail("Unknown command: {$cmd}. Run with --help.", 1),
    };
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------

function cmdMigrate(array $flags): void
{
    $dryRun = isset($flags['dry-run']);

    if (!$dryRun) {
        acquireLock('migrate');
    }

    $state   = loadRawState();
    $version = $state['version'] ?? 0;

    if ($version >= SCHEMA_VERSION) {
        echo "State already at schema v" . SCHEMA_VERSION . ".\n";
        if (!$dryRun) releaseLock();
        exit(0);
    }

    if ($version !== 1) {
        fail("Cannot migrate: state is schema v{$version} (expected v1 or v2).", 2);
    }

    if (!$dryRun) {
        file_put_contents(bakFile(), file_get_contents(stateFile()));
        echo "Backup written: " . bakFile() . "\n";
    }

    $v2 = migrateStateToV2($state);

    validateState($v2);
    validateMigrateCountPreservation($state, $v2);

    if ($dryRun) {
        echo json_encode($v2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        saveState($v2);
        releaseLock();
        echo "Migration complete. State is now schema v" . SCHEMA_VERSION . ".\n";
    }
}

function cmdNext(array $flags): void
{
    $state   = loadState();
    $execute = isset($flags['execute']);
    $flags['target'] = $flags['target'] ?? 'all';
    $only    = targetArg($flags, allowAll: true);

    $targetsToScan  = ($only === 'all') ? SUPPORTED_TARGETS : [$only];
    $onlyTarget     = ($only === 'all') ? null : $only;

    $action = resolveNext($state, $execute, $targetsToScan, $onlyTarget);

    echo $action['summary'] . "\n";
    if (!empty($action['detail'])) {
        echo "\n" . $action['detail'] . "\n";
    }
    if (!empty($action['hint'])) {
        echo "\nNext step: " . $action['hint'] . "\n";
    }
}

function cmdStatus(array $flags): void
{
    $state = loadState();

    echo "Upstream sync — {$state['upstream']}\n";
    echo "Upstream watermarks:\n";
    foreach (SUPPORTED_UPSTREAM_BRANCHES as $ub) {
        $wm    = $state['upstreamWatermarks'][$ub] ?? [];
        $lastAt  = $wm['lastCheckedAt'] ?? null;
        $lastSha = $wm['lastCheckedSha'] ?? null;
        if ($lastAt === null) {
            echo "  upstream/{$ub}: never\n";
        } else {
            $ago    = humanAgo($lastAt);
            $shaStr = $lastSha ? ', sha ' . substr($lastSha, 0, 7) : '';
            echo "  upstream/{$ub}: {$ago}{$shaStr} ({$lastAt})\n";
        }
    }
    echo str_repeat('=', 70) . "\n";

    $onlyTarget = null;
    if (isset($flags['target']) && $flags['target'] !== 'all') {
        assertTarget($flags['target']);
        $onlyTarget = $flags['target'];
    }
    $targets = $onlyTarget ? [$onlyTarget] : SUPPORTED_TARGETS;

    foreach ($targets as $target) {
        $counts             = ['pending' => 0, 'in-progress' => 0, 'backported' => 0, 'skipped' => 0];
        $pendingList        = [];
        $inProgressList     = [];
        $awaitingDownstream = [];
        $awaitingPropagate  = [];
        $hasEntries         = false;

        foreach ($state['prs'] as $entryId => $entry) {
            $ts = $entry['targets'][$target] ?? null;
            if ($ts === null) continue;
            $hasEntries = true;

            $status = $ts['status'] ?? 'pending';
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($status === 'pending') {
                $pendingList[$entryId] = $entry;
            } elseif ($status === 'in-progress') {
                $inProgressList[$entryId] = $entry;
            } elseif ($status === 'backported') {
                $opens   = openDownstreamPrs($entry, $target);
                if ($opens) $awaitingDownstream[$entryId] = $opens;
                $missing = missingDownstreamRepos($state, $entryId, $target);
                if ($missing) $awaitingPropagate[$entryId] = $missing;
            }
        }

        echo "\n── Target: {$target} ──\n";
        if (!$hasEntries) {
            echo "(no entries)\n";
            continue;
        }

        printf("Counts: pending=%d, in-progress=%d, backported=%d, skipped=%d\n",
            $counts['pending'], $counts['in-progress'], $counts['backported'], $counts['skipped']);

        if ($pendingList) {
            ksort($pendingList, SORT_NUMERIC);
            echo "\nPending backports (" . count($pendingList) . "):\n";
            foreach ($pendingList as $n => $p) {
                $pts  = $p['targets'][$target] ?? [];
                $conf = !empty($pts['confidence']) ? " [{$pts['confidence']}]" : '';
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
                echo "  #{$n}: " . implode(', ', array_keys($opens)) . "\n";
            }
        }

        if ($awaitingPropagate) {
            echo "\nNeeds propagation:\n";
            foreach ($awaitingPropagate as $n => $repos) {
                echo "  #{$n}: " . implode(', ', $repos) . "\n";
            }
        }
    }
    echo "\n";
}

function cmdPollDownstream(array $flags): void
{
    $state   = loadState();
    $only    = $flags['pr'] ?? null;
    $flags['target'] = $flags['target'] ?? 'all';
    $targetFilter = targetArg($flags, allowAll: true);
    $onlyTarget   = ($targetFilter === 'all') ? null : $targetFilter;

    $checked = 0;
    $updated = 0;

    foreach ($state['prs'] as $entryId => &$entry) {
        if ($only && (string)$entryId !== (string)$only) continue;

        foreach (SUPPORTED_TARGETS as $target) {
            if ($onlyTarget !== null && $target !== $onlyTarget) continue;
            $ts = $entry['targets'][$target] ?? null;
            if (!$ts || ($ts['status'] ?? null) !== 'backported') continue;
            if (empty($entry['targets'][$target]['downstreamPrs'])) continue;

            foreach ($entry['targets'][$target]['downstreamPrs'] as $repoId => &$dp) {
                if (($dp['status'] ?? null) !== 'open') continue;
                if (empty($dp['url'])) continue;
                $checked++;

                $merged = ghIsPrMerged($dp['url']);
                if ($merged === null) {
                    fwrite(STDERR, "  ! could not check {$dp['url']}\n");
                    continue;
                }
                if ($merged) {
                    $dp['status']   = 'merged';
                    $dp['mergedAt'] = date('c');
                    $updated++;
                    echo "  ✓ #{$entryId} [{$target}] {$repoId}: merged ({$dp['url']})\n";
                }
            }
            unset($dp);
        }
    }
    unset($entry);

    if ($updated > 0) {
        saveState($state);
    }

    echo "\nChecked {$checked} open downstream PR(s); updated {$updated}.\n";
}

function cmdDownstreamSet(array $args, array $flags): void
{
    if (count($args) < 2) {
        fail("Usage: downstream-set <entry> <repo> [<repo>...] [--target=BRANCH] --status=STATUS [--number=N] [--url=URL] [--reason=TEXT]", 1);
    }
    $entryId = $args[0];
    $repos   = array_slice($args, 1);
    $target  = targetArg($flags);
    $status  = $flags['status'] ?? 'merged';

    if (!in_array($status, ['open', 'merged', 'skipped'], true)) {
        fail("Invalid --status: {$status}", 1);
    }

    $state = loadState();
    if (!isset($state['prs'][$entryId])) {
        fail("Entry #{$entryId} not found in state.", 2);
    }

    $now = date('c');
    foreach ($repos as $repo) {
        if (!validDownstreamRepo($state, $repo)) {
            fail("Unknown downstream repo: {$repo}", 2);
        }

        $repoInfo = null;
        foreach ($state['downstreamRepos'] as $r) {
            if ($r['id'] === $repo) { $repoInfo = $r; break; }
        }
        $tracksBranch = $repoInfo['tracksBranch'] ?? null;
        if ($tracksBranch !== $target) {
            fail("Repo {$repo} tracks branch '{$tracksBranch}' but --target={$target}. Add --target={$tracksBranch} or change tracksBranch first.", 2);
        }

        // Auto-create target slot with status:pending if absent (mirrors setTargetState path)
        if (!isset($state['prs'][$entryId]['targets'][$target])) {
            $state['prs'][$entryId]['targets'][$target] = ['status' => 'pending'];
        }

        $existing = $state['prs'][$entryId]['targets'][$target]['downstreamPrs'][$repo] ?? [];
        $dpEntry  = array_merge($existing, ['status' => $status]);

        if ($status === 'skipped') {
            $dpEntry['reason'] = $flags['reason'] ?? ($existing['reason'] ?? 'skipped');
            unset($dpEntry['number'], $dpEntry['url'], $dpEntry['mergedAt']);
        } else {
            if (isset($flags['number'])) $dpEntry['number'] = (int)$flags['number'];
            if (isset($flags['url']))    $dpEntry['url']    = $flags['url'];
            if (!isset($dpEntry['propagatedAt'])) $dpEntry['propagatedAt'] = $now;
            if ($status === 'merged' && !isset($dpEntry['mergedAt'])) {
                $dpEntry['mergedAt'] = $now;
            }
        }

        $state['prs'][$entryId]['targets'][$target]['downstreamPrs'][$repo] = $dpEntry;
        echo "  ✓ #{$entryId} {$repo} [{$target}]: {$status}\n";
    }

    saveState($state);
}

function cmdPrSet(array $args, array $flags): void
{
    if (count($args) < 1) {
        fail("Usage: pr-set <entry> [--target=BRANCH] [--status=...] [--fork-pr=URL] [--confidence=...] [--notes=TEXT] [--reason=TEXT]", 1);
    }
    $entryId = $args[0];
    $target  = targetArg($flags);

    $state = loadState();
    if (!isset($state['prs'][$entryId])) {
        fail("Entry #{$entryId} not found in state.", 2);
    }

    $patch = [];
    if (isset($flags['status'])) {
        if (!in_array($flags['status'], ['pending', 'in-progress', 'backported', 'skipped'], true)) {
            fail("Invalid --status: {$flags['status']}", 1);
        }
        $patch['status'] = $flags['status'];
        if ($flags['status'] === 'backported') {
            $existing = getTargetState($state, $entryId, $target);
            if (empty($existing['backportedAt'])) {
                $patch['backportedAt'] = date('c');
            }
        }
    }
    if (isset($flags['fork-pr']))    $patch['forkPR']          = $flags['fork-pr'];
    if (isset($flags['confidence'])) $patch['confidence']      = $flags['confidence'];
    if (isset($flags['notes']))      $patch['adaptationNotes'] = $flags['notes'];
    if (isset($flags['reason']))     $patch['reason']          = $flags['reason'];

    if (empty($patch)) {
        fail("No fields to update. Provide at least one flag.", 1);
    }

    setTargetState($state, $entryId, $target, $patch);
    saveState($state);
    echo "  ✓ #{$entryId} [{$target}] updated\n";
}

function cmdTouchChecked(array $flags): void
{
    $ub  = upstreamBranchArg($flags);
    $sha = $flags['sha'] ?? null;

    $state = loadState();
    $state['upstreamWatermarks'][$ub]['lastCheckedAt'] = date('c');
    if ($sha !== null) {
        $state['upstreamWatermarks'][$ub]['lastCheckedSha'] = $sha;
    }
    saveState($state);

    $shaNote = $sha ? " (sha: {$sha})" : '';
    echo "upstreamWatermarks.{$ub}.lastCheckedAt = {$state['upstreamWatermarks'][$ub]['lastCheckedAt']}{$shaNote}\n";
}

function cmdLock(array $args, array $flags): void
{
    $action = $args[0] ?? null;
    if ($action === 'acquire') {
        $op = $flags['operation'] ?? 'unknown';
        acquireLock($op, !empty($flags['force']));
        echo "Lock acquired ({$op}).\n";
    } elseif ($action === 'release') {
        releaseLock();
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
 *  2. Propagate: backported PRs with fork PR merged and missing downstream coverage.
 *  3. Wait: backported PRs with open downstream PRs.
 *  4. Backport: lowest-numbered pending PR (start before start-teams).
 *  5. Triage: pick upstream branch with oldest lastCheckedAt.
 *
 * @param array<string> $targets
 * @param string|null   $onlyTarget null = all
 * @return array{summary:string,detail:string,hint:string}
 */
function resolveNext(array &$state, bool $execute, array $targets, ?string $onlyTarget): array
{
    // 1. Auto-poll GitHub when --execute is on.
    if ($execute) {
        $polled = pollAndUpdateDownstreams($state, $onlyTarget);
        if ($polled['updated'] > 0) {
            saveState($state);
            return [
                'summary' => "Updated {$polled['updated']} downstream PR(s) to merged.",
                'detail'  => $polled['log'],
                'hint'    => 'Run /us-next again to continue.',
            ];
        }
    }

    // 2. Propagation needed — iterates SUPPORTED_TARGETS order (start first).
    foreach (iterateTargets($state, $onlyTarget) as [$entryId, $target, $ts, $entry]) {
        if (($ts['status'] ?? null) !== 'backported') continue;
        $missing = missingDownstreamRepos($state, $entryId, $target);
        if (!$missing) continue;

        $forkPR = $ts['forkPR'] ?? null;
        if ($forkPR) {
            $forkMerged = ghIsPrMerged($forkPR);
            if ($forkMerged === false) continue;
        }

        $targetFlag = ($target !== 'start') ? " --target={$target}" : '';
        return [
            'summary' => "Propagate #{$entryId} [{$target}] to: " . implode(', ', $missing),
            'detail'  => "Fork PR: " . ($forkPR ?? '(none)') . "\nTitle: {$entry['title']}",
            'hint'    => "/us-propagate{$targetFlag} {$entryId} " . implode(' ', $missing),
        ];
    }

    // 3. Anything waiting?
    $waiting = [];
    foreach (iterateTargets($state, $onlyTarget) as [$entryId, $target, $ts, $entry]) {
        if (($ts['status'] ?? null) !== 'backported') continue;
        $opens = openDownstreamPrs($entry, $target);
        if ($opens) {
            $waiting[] = "#{$entryId} [{$target}]: open downstream PRs in " . implode(', ', array_keys($opens));
        }
    }

    // 4. Pending backports — start before start-teams, lowest number first.
    $pendingByTarget = [];
    foreach (iterateTargets($state, $onlyTarget) as [$entryId, $target, $ts, $entry]) {
        if (($ts['status'] ?? null) === 'pending') {
            $pendingByTarget[$target][$entryId] = $entry;
        }
    }
    foreach ($targets as $target) {
        if (empty($pendingByTarget[$target])) continue;
        $pending = $pendingByTarget[$target];
        ksort($pending, SORT_NUMERIC);
        $entryId = array_key_first($pending);
        $pr      = $pending[$entryId];
        $pts     = $pr['targets'][$target];
        $extra   = '';
        if (!empty($pts['adaptationNotes'])) $extra .= "\nNotes: {$pts['adaptationNotes']}";
        if (!empty($pts['confidence']))      $extra .= "\nConfidence: {$pts['confidence']}";
        $waitNote   = $waiting ? "\n\nMeanwhile, awaiting:\n  " . implode("\n  ", $waiting) : '';
        $targetFlag = ($target !== 'start') ? " --target={$target}" : '';

        return [
            'summary' => "Backport #{$entryId} [{$target}]: {$pr['title']}",
            'detail'  => "URL: " . ($pr['url'] ?? '') . "{$extra}{$waitNote}",
            'hint'    => "/us-backport {$entryId}{$targetFlag}",
        ];
    }

    if ($waiting) {
        return [
            'summary' => "Awaiting downstream merges (" . count($waiting) . "):",
            'detail'  => "  " . implode("\n  ", $waiting),
            'hint'    => 'Run /us-next --execute to poll GitHub, or wait and re-check.',
        ];
    }

    // 5. Nothing in queue — triage.
    // When a specific fork target is requested, prefer its paired upstream branch.
    // When scanning all, pick the upstream branch with the oldest lastCheckedAt.
    $watermarks = $state['upstreamWatermarks'] ?? [];
    if ($onlyTarget !== null) {
        $oldestBranch = TARGET_UPSTREAM_BRANCH[$onlyTarget] ?? 'main';
    } else {
        $oldestBranch = 'main';
        $oldestTs     = PHP_INT_MAX;
        foreach (SUPPORTED_UPSTREAM_BRANCHES as $ub) {
            $at = $watermarks[$ub]['lastCheckedAt'] ?? null;
            $ts = $at ? strtotime($at) : 0; // null treated as epoch = oldest
            if ($ts < $oldestTs) {
                $oldestTs     = $ts;
                $oldestBranch = $ub;
            }
        }
    }
    $wm     = $watermarks[$oldestBranch] ?? [];
    $lastAt = $wm['lastCheckedAt'] ?? null;
    $upstreamBranchFlag = ($oldestBranch !== 'main') ? " --upstream-branch={$oldestBranch}" : '';

    return [
        'summary' => "Queue empty — time to triage upstream/{$oldestBranch}.",
        'detail'  => 'Last triage: ' . ($lastAt ? humanAgo($lastAt) . " ({$lastAt})" : 'never'),
        'hint'    => "/us-triage{$upstreamBranchFlag}",
    ];
}

/**
 * @return array{updated:int,log:string}
 */
function pollAndUpdateDownstreams(array &$state, ?string $onlyTarget): array
{
    $log     = [];
    $updated = 0;

    foreach ($state['prs'] as $entryId => &$entry) {
        foreach (SUPPORTED_TARGETS as $target) {
            if ($onlyTarget !== null && $target !== $onlyTarget) continue;
            $ts = $entry['targets'][$target] ?? null;
            if (!$ts || ($ts['status'] ?? null) !== 'backported') continue;
            if (empty($entry['targets'][$target]['downstreamPrs'])) continue;

            foreach ($entry['targets'][$target]['downstreamPrs'] as $repoId => &$dp) {
                if (($dp['status'] ?? null) !== 'open') continue;
                if (empty($dp['url'])) continue;

                $merged = ghIsPrMerged($dp['url']);
                if ($merged === true) {
                    $dp['status']   = 'merged';
                    $dp['mergedAt'] = date('c');
                    $log[]          = "  #{$entryId} [{$target}] {$repoId}: merged";
                    $updated++;
                }
            }
            unset($dp);
        }
    }
    unset($entry);

    return ['updated' => $updated, 'log' => implode("\n", $log)];
}

// ---------------------------------------------------------------------------
// Migration: v1 → v2
// ---------------------------------------------------------------------------

/**
 * @param array<mixed> $state
 * @return array<mixed>
 */
function migrateStateToV2(array $state): array
{
    $migratedAt = date('c');

    $v2 = [
        'version'  => SCHEMA_VERSION,
        'upstream' => $state['upstream'],
        'upstreamWatermarks' => [
            'main'  => [
                'lastCheckedAt'  => $state['lastCheckedAt'] ?? null,
                'lastCheckedSha' => null,
            ],
            'teams' => [
                'lastCheckedAt'  => null,
                'lastCheckedSha' => null,
            ],
        ],
        'downstreamRepos' => array_map(
            fn(array $repo) => array_merge($repo, ['tracksBranch' => 'start']),
            $state['downstreamRepos'] ?? []
        ),
        'prs' => [],
    ];

    foreach ($state['prs'] as $entryId => $entry) {
        $v2Entry = [
            'title' => $entry['title'] ?? '',
            'url'   => $entry['url'] ?? '',
            'sourceRefs' => [
                'main' => [
                    'type'     => 'pr',
                    'id'       => (string)$entryId,
                    'url'      => $entry['url'] ?? '',
                    'mergedAt' => $entry['upstreamMergedAt'] ?? null,
                ],
            ],
        ];

        // Build targets.start
        $targetStart = ['status' => $entry['status'] ?? 'pending'];

        if (isset($entry['backportedAt']))    $targetStart['backportedAt']    = $entry['backportedAt'];
        // Normalize forkPr/forkPR casing → forkPR
        $forkPr = $entry['forkPR'] ?? $entry['forkPr'] ?? null;
        if ($forkPr !== null)                 $targetStart['forkPR']          = $forkPr;
        if (isset($entry['confidence']))      $targetStart['confidence']      = $entry['confidence'];
        if (isset($entry['adaptationNotes'])) $targetStart['adaptationNotes'] = $entry['adaptationNotes'];
        if (isset($entry['reason']))          $targetStart['reason']          = $entry['reason'];
        if (isset($entry['downstreamPrs']))   $targetStart['downstreamPrs']   = $entry['downstreamPrs'];

        // Migrate legacy downstreams: {repo: url} → downstreamPrs with status:open
        if (isset($entry['downstreams'])) {
            $targetStart['downstreamPrs'] = $targetStart['downstreamPrs'] ?? [];
            foreach ($entry['downstreams'] as $repoId => $url) {
                $num = null;
                if (preg_match('#/pull/(\d+)#', $url, $m)) {
                    $num = (int)$m[1];
                }
                $targetStart['downstreamPrs'][$repoId] = [
                    'number'       => $num,
                    'status'       => 'open',
                    'url'          => $url,
                    'propagatedAt' => $entry['backportedAt'] ?? $migratedAt,
                ];
            }
        }

        $v2Entry['targets'] = ['start' => $targetStart];
        $v2['prs'][(string)$entryId] = $v2Entry;
    }

    return $v2;
}

/**
 * Invariant #8 (migrate-only): count of each status across targets[*] must match
 * the v1 count of entries at that status.
 *
 * @param array<mixed> $v1State
 * @param array<mixed> $v2State
 */
function validateMigrateCountPreservation(array $v1State, array $v2State): void
{
    $v1Counts = ['pending' => 0, 'in-progress' => 0, 'backported' => 0, 'skipped' => 0];
    foreach ($v1State['prs'] ?? [] as $entry) {
        $st = $entry['status'] ?? 'pending';
        $v1Counts[$st] = ($v1Counts[$st] ?? 0) + 1;
    }

    $v2Counts = ['pending' => 0, 'in-progress' => 0, 'backported' => 0, 'skipped' => 0];
    foreach ($v2State['prs'] ?? [] as $entry) {
        foreach ($entry['targets'] ?? [] as $ts) {
            $st = $ts['status'] ?? 'pending';
            $v2Counts[$st] = ($v2Counts[$st] ?? 0) + 1;
        }
    }

    foreach ($v1Counts as $st => $count) {
        $v2Count = $v2Counts[$st] ?? 0;
        if ($v2Count !== $count) {
            fail("validateMigrateCountPreservation: status '{$st}' count mismatch (v1={$count}, v2={$v2Count})", 2);
        }
    }
}

// ---------------------------------------------------------------------------
// State I/O
// ---------------------------------------------------------------------------

/**
 * Load state without any version guard. Only cmdMigrate should use this.
 *
 * @return array<mixed>
 */
function loadRawState(): array
{
    $file = stateFile();
    if (!file_exists($file)) fail("State file not found: {$file}", 2);
    $state = json_decode(file_get_contents($file), true);
    if (!is_array($state)) fail("State file is invalid JSON.", 2);
    return $state;
}

/**
 * Load state and enforce schema v2. Fails with migration hint if v1 is found.
 *
 * @return array<mixed>
 */
function loadState(): array
{
    $state   = loadRawState();
    $version = $state['version'] ?? 0;
    if ($version !== SCHEMA_VERSION) {
        fail(
            "State file is schema v{$version}; expected v" . SCHEMA_VERSION .
            ". Run: php " . basename(__FILE__) . " migrate",
            2
        );
    }
    validateState($state);
    return $state;
}

/**
 * Run structural invariant checks on a v2 state.
 *
 * Invariants 1–7 from the design. Invariant 8 (count preservation)
 * is checked separately in validateMigrateCountPreservation() (migrate only).
 *
 * @param array<mixed> $state
 */
function validateState(array $state): void
{
    $validStatuses = ['pending', 'in-progress', 'backported', 'skipped'];

    // Invariant 1: version === SCHEMA_VERSION
    if (($state['version'] ?? 0) !== SCHEMA_VERSION) {
        fail("validateState: version must be " . SCHEMA_VERSION . ", got " . json_encode($state['version'] ?? null), 2);
    }

    // Invariant 6: upstreamWatermarks keys ⊆ SUPPORTED_UPSTREAM_BRANCHES; main required
    $watermarks = $state['upstreamWatermarks'] ?? null;
    if (!is_array($watermarks)) {
        fail("validateState: upstreamWatermarks must be an array", 2);
    }
    if (!isset($watermarks['main'])) {
        fail("validateState: upstreamWatermarks.main is required", 2);
    }
    foreach (array_keys($watermarks) as $wk) {
        if (!in_array($wk, SUPPORTED_UPSTREAM_BRANCHES, true)) {
            fail("validateState: upstreamWatermarks key '{$wk}' not in supported upstream branches", 2);
        }
    }

    // Invariant 5: downstreamRepos[*].tracksBranch ∈ SUPPORTED_TARGETS
    foreach ($state['downstreamRepos'] ?? [] as $idx => $repo) {
        $tb = $repo['tracksBranch'] ?? null;
        if ($tb === null || !in_array($tb, SUPPORTED_TARGETS, true)) {
            fail("validateState: downstreamRepos[{$idx}] tracksBranch '{$tb}' not in supported targets", 2);
        }
    }

    $legacyFields = [
        'appliesTo', 'forkPr', 'downstreams', 'lastCheckedAt',
        'status', 'backportedAt', 'upstreamMergedAt', 'downstreamPrs',
    ];

    foreach ($state['prs'] ?? [] as $entryId => $entry) {
        // Invariant 2: every entry has non-empty targets with keys ⊆ SUPPORTED_TARGETS
        $targets = $entry['targets'] ?? [];
        if (empty($targets)) {
            fail("validateState: prs[{$entryId}] has no targets", 2);
        }
        foreach (array_keys($targets) as $tk) {
            if (!in_array($tk, SUPPORTED_TARGETS, true)) {
                fail("validateState: prs[{$entryId}].targets contains unsupported key '{$tk}' (allowed: " . implode(', ', SUPPORTED_TARGETS) . ")", 2);
            }
        }

        // Invariant 3: every targets[<X>].status is valid
        foreach ($targets as $targetName => $targetState) {
            $st = $targetState['status'] ?? null;
            if (!in_array($st, $validStatuses, true)) {
                fail("validateState: prs[{$entryId}].targets[{$targetName}].status '{$st}' is invalid", 2);
            }
        }

        // Invariant 4: sourceRefs keys ⊆ SUPPORTED_UPSTREAM_BRANCHES; at least one entry
        $sourceRefs = $entry['sourceRefs'] ?? [];
        if (empty($sourceRefs)) {
            fail("validateState: prs[{$entryId}].sourceRefs is empty or missing", 2);
        }
        foreach (array_keys($sourceRefs) as $srk) {
            if (!in_array($srk, SUPPORTED_UPSTREAM_BRANCHES, true)) {
                fail("validateState: prs[{$entryId}].sourceRefs key '{$srk}' not in supported upstream branches", 2);
            }
        }

        // Invariant 7: no legacy top-level fields in entry
        foreach ($legacyFields as $lf) {
            if (array_key_exists($lf, $entry)) {
                fail("validateState: prs[{$entryId}] contains legacy field '{$lf}'; migration may be incomplete", 2);
            }
        }
    }
}

/**
 * @param array<mixed> $state
 */
function saveState(array $state): void
{
    $file = stateFile();
    $tmp  = $file . '.tmp';
    file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    rename($tmp, $file);
}

// ---------------------------------------------------------------------------
// Accessor helpers
// ---------------------------------------------------------------------------

/**
 * @param array<mixed> $state
 * @return array<mixed>|null
 */
function getTargetState(array $state, string $entryId, string $target): ?array
{
    return $state['prs'][$entryId]['targets'][$target] ?? null;
}

/**
 * Sets target state, creating targets[$target] with status:pending if missing.
 *
 * @param array<mixed> $state
 * @param array<mixed> $patch
 */
function setTargetState(array &$state, string $entryId, string $target, array $patch): void
{
    if (!isset($state['prs'][$entryId]['targets'][$target])) {
        $state['prs'][$entryId]['targets'][$target] = ['status' => 'pending'];
    }
    $state['prs'][$entryId]['targets'][$target] = array_merge(
        $state['prs'][$entryId]['targets'][$target],
        $patch
    );
}

/**
 * Yields [$entryId, $target, $targetState, $entry] for entries that have a slot.
 *
 * @param array<mixed> $state
 * @return \Generator<int, array<mixed>>
 */
function iterateTargets(array $state, ?string $onlyTarget = null): \Generator
{
    foreach ($state['prs'] as $entryId => $entry) {
        foreach (SUPPORTED_TARGETS as $target) {
            if ($onlyTarget !== null && $target !== $onlyTarget) continue;
            if (!isset($entry['targets'][$target])) continue;
            yield [$entryId, $target, $entry['targets'][$target], $entry];
        }
    }
}

/**
 * Read --target from flags. Default: 'start'. --target=all allowed only if $allowAll.
 *
 * @param array<mixed> $flags
 */
function targetArg(array $flags, bool $allowAll = false): string
{
    $t = $flags['target'] ?? 'start';
    if ($t === 'all') {
        if (!$allowAll) fail("--target=all is not allowed for this command (mutating).", 1);
        return 'all';
    }
    assertTarget($t);
    return $t;
}

/**
 * Read --upstream-branch from flags. Default: 'main'.
 *
 * @param array<mixed> $flags
 */
function upstreamBranchArg(array $flags): string
{
    $b = $flags['upstream-branch'] ?? 'main';
    assertUpstreamBranch($b);
    return $b;
}

function assertTarget(string $t): void
{
    if (!in_array($t, SUPPORTED_TARGETS, true)) {
        fail("Invalid --target '{$t}'. Valid: " . implode(', ', SUPPORTED_TARGETS), 1);
    }
}

function assertUpstreamBranch(string $b): void
{
    if (!in_array($b, SUPPORTED_UPSTREAM_BRANCHES, true)) {
        fail("Invalid --upstream-branch '{$b}'. Valid: " . implode(', ', SUPPORTED_UPSTREAM_BRANCHES), 1);
    }
}

/**
 * @param array<mixed> $state
 * @return array<mixed>
 */
function activeDownstreamReposForTarget(array $state, string $target): array
{
    return array_values(array_filter(
        $state['downstreamRepos'] ?? [],
        fn(array $r) => ($r['tracksBranch'] ?? null) === $target && ($r['status'] ?? 'active') === 'active'
    ));
}

/**
 * @param array<mixed> $state
 * @return array<string>
 */
function missingDownstreamRepos(array $state, string $entryId, string $target): array
{
    $entry    = $state['prs'][$entryId] ?? [];
    $covered  = array_keys($entry['targets'][$target]['downstreamPrs'] ?? []);
    $active   = activeDownstreamReposForTarget($state, $target);
    $activeIds = array_map(fn(array $r) => $r['id'], $active);
    return array_values(array_diff($activeIds, $covered));
}

/**
 * Returns open downstream PRs for the given target.
 *
 * @param array<mixed> $entry
 * @return array<mixed>
 */
function openDownstreamPrs(array $entry, string $target): array
{
    $opens = [];
    foreach ($entry['targets'][$target]['downstreamPrs'] ?? [] as $repo => $dp) {
        if (($dp['status'] ?? null) === 'open') $opens[$repo] = $dp;
    }
    return $opens;
}

// ---------------------------------------------------------------------------
// Lock helpers
// ---------------------------------------------------------------------------

function acquireLock(string $operation, bool $force = false): void
{
    $lf = lockFile();
    if (file_exists($lf) && !$force) {
        $existing = json_decode(file_get_contents($lf), true) ?: [];
        $age      = time() - strtotime($existing['startedAt'] ?? 'now');
        if ($age < STALE_LOCK_MINUTES * 60) {
            $mins = (int)($age / 60);
            fail("Lock held by '{$existing['operation']}' for {$mins}m. Use --force to override.", 3);
        }
    }
    file_put_contents($lf, json_encode([
        'pid'       => gethostname() . ':' . getmypid(),
        'startedAt' => date('c'),
        'operation' => $operation,
    ], JSON_PRETTY_PRINT) . "\n");
}

function releaseLock(): void
{
    $lf = lockFile();
    if (file_exists($lf)) unlink($lf);
}

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

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
    [, $owner, $repo, $num] = $m;
    $repoArg = escapeshellarg("{$owner}/{$repo}");
    $out     = shell_exec("gh pr view {$num} --repo {$repoArg} --json state 2>/dev/null");
    if (!$out) return null;
    $data  = json_decode($out, true);
    $ghState = $data['state'] ?? null;
    if ($ghState === 'MERGED') return true;
    if ($ghState === 'OPEN') return false;
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

/**
 * @param array<string> $rest
 * @return array{array<string>, array<string, mixed>}
 */
function parseArgs(array $rest): array
{
    $args  = [];
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
  migrate [--dry-run]
    Upgrade state from schema v1 to v2. With --dry-run: prints transformed
    state to stdout, does not save, does not write .bak.

  next [--execute] [--target=start|start-teams|all]
    Resolve and print the next workflow action.
    Defaults: --target=all (walks start then start-teams).

  status [--target=start|start-teams|all]
    Summary per fork branch. Shows watermarks per upstream branch.
    Defaults: --target=all (shows both sections).

  poll-downstream [--pr=ID] [--target=start|start-teams|all]
    Update merged-on-GitHub downstream PRs.
    Defaults: --target=all.

  downstream-set <entry> <repo>... [--target=BRANCH] --status=open|merged|skipped
                                   [--number=N] [--url=URL] [--reason=TEXT]
    Mark downstream PR(s) for a target branch. Defaults: --target=start.

  pr-set <entry> [--target=BRANCH] [--status=pending|in-progress|backported|skipped]
                 [--fork-pr=URL] [--confidence=high|medium|low]
                 [--notes=TEXT] [--reason=TEXT]
    Update a PR entry's per-target state. Defaults: --target=start.

  touch-checked [--upstream-branch=main|teams] [--sha=SHA]
    Set watermark for an upstream branch to now.
    Defaults: --upstream-branch=main.

  lock acquire|release [--operation=NAME] [--force]
    Manage lock file. acquire flags: --operation=NAME [--force]

Flags:
  --target=BRANCH         Fork-side branch scope (start, start-teams, or all on
                          query commands). Mutating commands reject all.
  --upstream-branch=NAME  Upstream tracking branch (main or teams).
                          Only for touch-checked.

State file: .starter-kit/upstream-sync.json
Override:   SYNC_STATE_FILE environment variable
TXT;
    echo "\n";
}
