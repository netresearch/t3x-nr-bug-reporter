<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Error;

use Netresearch\NrBugReporter\Attribution\AttributionResult;
use Netresearch\NrBugReporter\Attribution\PackageAttributionService;
use Netresearch\NrBugReporter\Capture\CapturedError;
use Netresearch\NrBugReporter\Capture\SessionStore;
use Netresearch\NrBugReporter\Decision\ReportPolicy;
use Netresearch\NrBugReporter\Report\IssueUrlComposer;
use Netresearch\NrBugReporter\Resolver\GitHubTrackerResolver;
use Netresearch\NrBugReporter\Resolver\TrackerEndpoint;
use Netresearch\NrBugReporter\Service\PackageIndexProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Shared by the exception handlers: attribute an uncaught error to its originating Composer package,
 * store it in the BE session for the toolbar, and compose the report URL when a one-click report is
 * appropriate. All of it is wrapped in try/catch so the reporter can never break core error rendering.
 */
trait CapturesUncaughtErrors
{
    private ?CapturedError $captured = null;

    private ?string $reportUrl = null;

    private function captureQuietly(\Throwable $exception): void
    {
        try {
            $this->capture($exception);
        } catch (\Throwable) {
            // Never let the reporter interfere with the actual error being handled.
        }
    }

    private function capture(\Throwable $exception): void
    {
        $index = GeneralUtility::makeInstance(PackageIndexProvider::class)->get();

        $frames = $this->framesFromThrowable($exception);
        $rootCause = $this->rootCauseFrames($exception);
        $result = (new PackageAttributionService($index))->attribute($frames, $rootCause);

        $endpoint = $result->culprit !== null && $result->culprit !== AttributionResult::CORE
            ? (new GitHubTrackerResolver($index))->resolve($result->culprit)
            : new TrackerEndpoint(null, null, 'none', 'no culprit');

        $decision = (new ReportPolicy())->decide(
            $result,
            $endpoint,
            count($rootCause ?? $frames),
            $exception::class,
            $exception->getMessage(),
        );

        $this->captured = new CapturedError(
            $exception::class,
            $exception->getMessage(),
            (int) $exception->getCode(),
            $exception->getFile(),
            $exception->getLine(),
            $result->culprit,
            $result->confidence,
            $endpoint->isActionable() ? $endpoint->url : null,
            $endpoint->status,
            (bool) $decision['offer'],
            time(),
        );

        GeneralUtility::makeInstance(SessionStore::class)->recordError($this->captured);

        if ($decision['offer'] && $endpoint->isActionable() && $endpoint->url !== null) {
            $this->reportUrl = GeneralUtility::makeInstance(IssueUrlComposer::class)
                ->composeForError($endpoint->url, $this->captured, null);
        }
    }

    /**
     * Build [throw-site, ...trace] frames in the {file, class} shape the attribution engine consumes.
     * The throw site uses getFile() for the location but getTrace()[0]['class'] for the RUNTIME class
     * (so a trait method resolves to the consuming package, not the trait's defining one).
     *
     * @return list<array{file:?string, class:?string}>
     */
    private function framesFromThrowable(\Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $frames = [[
            'file' => $exception->getFile(),
            'class' => isset($trace[0]['class']) && is_string($trace[0]['class']) ? $trace[0]['class'] : null,
        ]];

        foreach ($trace as $frame) {
            $frames[] = [
                'file' => isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : null,
                'class' => isset($frame['class']) && is_string($frame['class']) ? $frame['class'] : null,
            ];
        }

        return $frames;
    }

    /**
     * Walk the $previous chain to the deepest root cause; only return frames when re-wrapping occurred.
     *
     * @return list<array{file:?string, class:?string}>|null
     */
    private function rootCauseFrames(\Throwable $exception): ?array
    {
        $root = $exception;
        $wrapped = false;
        while ($root->getPrevious() !== null) {
            $root = $root->getPrevious();
            $wrapped = true;
        }

        return $wrapped ? $this->framesFromThrowable($root) : null;
    }
}
