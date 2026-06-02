#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Adversarial scorecard. Runs the red-team corpus (fixtures/adversarial.php) through the live
 * heuristic and flags every case where the heuristic's culprit DISAGREES with the expert culprit.
 *
 * Unlike bin/run.php (which proves self-consistency on a designed corpus), a mismatch here is a
 * genuine FINDING: a real-world trace shape the heuristic gets wrong. The point is to surface them.
 *
 * Usage:  php bin/adversarial.php
 */

use Netresearch\NrBugReporter\Attribution\PackageAttributionService;
use Netresearch\NrBugReporter\Attribution\PackageIndex;
use Netresearch\NrBugReporter\Resolver\GitHubTrackerResolver;
use Netresearch\NrBugReporter\Resolver\TrackerEndpoint;

/** @var PackageIndex $index */
$index = require __DIR__ . '/_bootstrap.php';

$fixture = __DIR__ . '/../fixtures/adversarial.php';
if (!is_file($fixture)) {
    fwrite(STDERR, "No adversarial fixture at {$fixture} (run the red-team workflow first).\n");
    exit(2);
}

$attribution = new PackageAttributionService($index);
/** @var array<string, string> $repoOverrides */
$repoOverrides = require __DIR__ . '/../fixtures/repo-overrides.php';
$resolver = new GitHubTrackerResolver($index, $repoOverrides);

/** @var list<array<string, mixed>> $traces */
$traces = require $fixture;

$findings = 0;
$agree = 0;
$predictionHits = 0;

echo "\n=== nr_bug_reporter — adversarial red-team scorecard ===\n";
echo "FINDING = heuristic culprit disagrees with the expert culprit (a real weakness to address).\n";
echo "OK      = heuristic matches the expert (robust against this attack).\n\n";

foreach ($traces as $t) {
    $result = $attribution->attribute($t['files']);
    $culprit = $result->culprit ?? 'NONE';
    $tracker = $result->culprit === null
        ? new TrackerEndpoint(null, null, 'none', 'no culprit')
        : $resolver->resolve($result->culprit);

    $expert = (string) $t['expertCulprit'];
    $predicted = (string) $t['predictedHeuristicCulprit'];

    $isFinding = $culprit !== $expert;
    $isFinding ? $findings++ : $agree++;
    if ($predicted === $culprit) {
        $predictionHits++;
    }

    printf("[%s] %-6s %s\n", $isFinding ? 'FIND' : 'OK', (string) $t['id'], (string) $t['title']);
    printf("        vector    : %s\n", (string) $t['attackVector']);
    printf(
        "        heuristic : %-30s conf=%-7s tracker=%s\n",
        $culprit,
        $result->confidence,
        $tracker->isActionable() ? (string) $tracker->url : '(' . $tracker->status . ')',
    );
    printf(
        "        expert    : %-30s redteam predicted: %s%s\n",
        $expert,
        $predicted,
        $predicted === $culprit ? '' : '   (prediction off)',
    );
    if ($result->alternatives() !== []) {
        printf("        ranked    : %s\n", implode('  >  ', $result->alternatives()));
    }
    printf("        %s\n\n", (string) $t['rationale']);
}

$total = $findings + $agree;
printf("=== %d FINDINGS (heuristic != expert) / %d agree, of %d adversarial traces ===\n", $findings, $agree, $total);
printf("=== red-team correctly predicted the heuristic output on %d/%d ===\n", $predictionHits, $total);
