#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Hardened scorecard. Runs the red-team scenarios through the hardened heuristic (class-FQCN
 * attribution + $previous-walk) and the ReportPolicy gate, and categorises each outcome:
 *
 *   FIXED   - right culprit AND right action (file to the correct party / correctly withhold)
 *   SAFE    - withheld a report the expert also did not want (config/private/core) - no harm
 *   MISSED  - withheld a report the expert wanted (harm-free, but lost value - a gating false negative)
 *   HARMFUL - offered a one-click report to the WRONG party (the failure mode we must avoid)
 *
 * For each scenario it also shows the flat-file "before" culprit, so the delta is visible.
 *
 * Usage:  php bin/hardened.php
 */

use Netresearch\NrBugReporter\Attribution\PackageAttributionService;
use Netresearch\NrBugReporter\Attribution\PackageIndex;
use Netresearch\NrBugReporter\Decision\ReportPolicy;
use Netresearch\NrBugReporter\Resolver\GitHubTrackerResolver;
use Netresearch\NrBugReporter\Resolver\TrackerEndpoint;

/** @var PackageIndex $index */
$index = require __DIR__ . '/_bootstrap.php';

/** @var array<string, string> $repoOverrides */
$repoOverrides = require __DIR__ . '/../fixtures/repo-overrides.php';

$attribution = new PackageAttributionService($index);
$resolver = new GitHubTrackerResolver($index, $repoOverrides);
$policy = new ReportPolicy();

/** @var list<array<string, mixed>> $traces */
$traces = require __DIR__ . '/../fixtures/hardened.php';

$flatFile = static fn (mixed $frame): ?string => is_array($frame) ? ($frame['file'] ?? null) : $frame;

$tally = ['FIXED' => 0, 'SAFE' => 0, 'MISSED' => 0, 'HARMFUL' => 0];

echo "\n=== nr_bug_reporter — hardened heuristic scorecard ===\n";
echo "class-FQCN attribution (trait fix) + \$previous-walk + ReportPolicy gating.\n";
echo "FIXED=right party+action  SAFE=correctly withheld  MISSED=over-withheld (harm-free)  HARMFUL=wrong button\n\n";

foreach ($traces as $t) {
    $flat = array_map($flatFile, $t['frames']);
    $before = $attribution->attribute($flat);                       // file-only, no $previous-walk
    $after = $attribution->attribute($t['frames'], $t['rootCause'] ?? null);

    $culprit = $after->culprit ?? 'NONE';
    $tracker = $after->culprit === null || $after->culprit === 'CORE'
        ? new TrackerEndpoint(null, null, 'none', 'no upstream tracker')
        : $resolver->resolve($after->culprit);
    $frameCount = count($t['rootCause'] ?? $t['frames']);
    $decision = $policy->decide($after, $tracker, $frameCount, $t['exceptionClass'], $t['message']);

    $expert = (string) $t['expertCulprit'];
    $expertActionable = (bool) $t['expertActionable'];
    $culpritOk = $culprit === $expert;
    $offered = $decision['offer'];

    if ($culpritOk && $offered === $expertActionable) {
        $verdict = 'FIXED';
    } elseif (!$offered && !$expertActionable) {
        $verdict = 'SAFE';
    } elseif (!$offered && $expertActionable) {
        $verdict = 'MISSED';
    } else {
        $verdict = 'HARMFUL';
    }
    $tally[$verdict]++;

    $beforeCulprit = $before->culprit ?? 'NONE';
    $delta = $beforeCulprit === $culprit ? '' : "  (before: {$beforeCulprit})";
    printf("[%-7s] %-6s %s\n", $verdict, (string) $t['id'], (string) $t['title']);
    printf("           after: %-30s conf=%-7s offer=%s — %s%s\n",
        $culprit, $after->confidence, $offered ? 'YES' : 'no', $decision['reason'], $delta);
    printf("           expert: %-30s actionable=%s\n", $expert, $expertActionable ? 'yes' : 'no');
}

$total = array_sum($tally);
echo "\n";
printf("=== %d FIXED · %d SAFE · %d MISSED · %d HARMFUL  (of %d) ===\n", $tally['FIXED'], $tally['SAFE'], $tally['MISSED'], $tally['HARMFUL'], $total);
$harmless = $tally['FIXED'] + $tally['SAFE'] + $tally['MISSED'];
printf("=== harmful one-click reports: 20 (before) -> %d (after); %d now correctly routed, %d safely withheld ===\n",
    $tally['HARMFUL'], $tally['FIXED'], $tally['SAFE'] + $tally['MISSED']);
