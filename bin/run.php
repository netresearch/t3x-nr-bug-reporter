#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scorecard runner for the PackageAttributionService spike.
 *
 * Builds a package index from the REAL TYPO3 core install (typo3-core-fix-105737), augments it
 * with locally-available extensions as if they were installed in a project, then runs the
 * attribution + GitHub tracker resolution against the trace corpus and prints a pass/fail report.
 *
 * Usage:  php bin/run.php
 */

use Netresearch\NrBugReporter\Attribution\PackageAttributionService;
use Netresearch\NrBugReporter\Attribution\PackageIndex;
use Netresearch\NrBugReporter\Resolver\GitHubTrackerResolver;
use Netresearch\NrBugReporter\Resolver\TrackerEndpoint;

/** @var PackageIndex $index */
$index = require __DIR__ . '/_bootstrap.php';

$attribution = new PackageAttributionService($index);
/** @var array<string, string> $repoOverrides */
$repoOverrides = require __DIR__ . '/../fixtures/repo-overrides.php';
$resolver = new GitHubTrackerResolver($index, $repoOverrides);

/** @var list<array<string, mixed>> $traces */
$traces = require __DIR__ . '/../fixtures/traces.php';

$pass = 0;
$fail = 0;

echo "\n=== nr_bug_reporter — PackageAttributionService spike ===\n";
echo 'Index: ' . count($index->all()) . " packages (real core installed.php + 5 augmented extensions)\n";
echo "Traces ordered innermost->outermost; files[0] = throw site (getFile()).\n\n";

foreach ($traces as $t) {
    $result = $attribution->attribute($t['files']);
    $culprit = $result->culprit;
    $tracker = $culprit === null
        ? new TrackerEndpoint(null, null, 'none', 'no culprit')
        : $resolver->resolve($culprit);

    $checks = [
        'culprit' => $result->culprit === $t['expectCulprit'],
        'confidence' => !isset($t['expectConfidence']) || $result->confidence === $t['expectConfidence'],
        'tracker' => !isset($t['expectTracker']) || $tracker->status === $t['expectTracker'],
        'actionable' => !isset($t['expectActionable']) || $tracker->isActionable() === $t['expectActionable'],
        'alternative' => !isset($t['expectAlternative']) || in_array($t['expectAlternative'], $result->alternatives(), true),
    ];
    $ok = !in_array(false, $checks, true);
    $ok ? $pass++ : $fail++;

    printf("[%s] %-4s %s\n", $ok ? 'PASS' : 'FAIL', $t['id'], $t['title']);
    printf("        culprit : %-32s conf=%s\n", $culprit ?? '(none)', $result->confidence);
    printf("        tracker : %-13s %s\n", $tracker->status, $tracker->url ?? ('— ' . $tracker->reason));
    if ($result->alternatives() !== []) {
        printf("        ranked  : %s\n", implode('  >  ', $result->alternatives()));
    }
    if (!$ok) {
        $bad = [];
        foreach ($checks as $name => $value) {
            if ($value === false) {
                $exp = $t['expect' . ucfirst($name)] ?? '(unset)';
                $bad[] = $name . '!=' . var_export($exp, true);
            }
        }
        printf("        \033[31mMISMATCH: %s\033[0m\n", implode(', ', $bad));
    }
    printf("        why     : %s\n\n", $t['why']);
}

// --- Project-install layout sanity check (sysext as real typo3-cms-framework packages, no monorepo paths) ---
$project = new PackageIndex();
$project->add('typo3/cms-core', '/srv/app/vendor/typo3/cms-core', 'typo3-cms-framework');
$project->add('typo3/cms-backend', '/srv/app/vendor/typo3/cms-backend', 'typo3-cms-framework');
$project->add('acme/shop', '/srv/app/vendor/acme/shop', 'typo3-cms-extension');
$projectResult = (new PackageAttributionService($project))->attribute([
    '/srv/app/vendor/typo3/cms-core/Classes/Database/Connection.php',
    '/srv/app/vendor/acme/shop/Classes/Domain/Repository/ProductRepository.php',
    '/srv/app/vendor/typo3/cms-backend/Classes/Http/RouteDispatcher.php',
]);
$projectOk = $projectResult->culprit === 'acme/shop' && $projectResult->confidence === 'high';
$projectOk ? $pass++ : $fail++;
printf("[%s] PRJ  Project-install layout (sysext = typo3-cms-framework packages, no monorepo paths)\n", $projectOk ? 'PASS' : 'FAIL');
printf("        culprit : %-32s conf=%s (expected acme/shop / high)\n\n", $projectResult->culprit ?? '(none)', $projectResult->confidence);

// --- Resolver fallback chain: each tier exercised against a representative real / curated package ---
echo "Resolver fallback chain (support.issues -> derived -> lock-source -> override-map -> reject):\n";
$chain = [
    ['netresearch/nr-textdb', 'support.issues'],   // composer.json support.issues (GitHub)
    ['firebase/php-jwt', 'derived'],               // derived from GitHub homepage
    ['doctrine/dbal', 'lock-source'],              // composer.lock / installed.json source url
    ['acme/closed-source', 'override-map'],        // curated last-resort map
    ['netresearch/nrc-template', 'rejected'],      // declared non-GitHub tracker -> never overridden
];
foreach ($chain as [$pkg, $expectStatus]) {
    $endpoint = $resolver->resolve($pkg);
    $ok = $endpoint->status === $expectStatus;
    $ok ? $pass++ : $fail++;
    printf(
        "[%s] %-26s -> %-14s %s\n",
        $ok ? 'PASS' : 'FAIL',
        $pkg,
        $endpoint->status,
        $endpoint->url ?? ('(' . $endpoint->reason . ')'),
    );
}
echo "\n";

$total = $pass + $fail;
printf("=== %d/%d passed ===\n", $pass, $total);

exit($fail === 0 ? 0 : 1);
