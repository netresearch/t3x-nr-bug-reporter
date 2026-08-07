<?php

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
use TYPO3\CMS\Core\Error\DebugExceptionHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extends the core DebugExceptionHandler so an uncaught error is (1) attributed to its originating
 * Composer package, (2) stored in the BE session for the toolbar, and (3) — when a one-click report
 * is appropriate — surfaced as a "Report this bug" banner on the rendered debug page.
 *
 * Instantiated by core via GeneralUtility::makeInstance() with NO arguments (no DI); the parent
 * constructor registers the handler with set_exception_handler(), so we do not declare a constructor.
 * All extra work is wrapped in try/catch so the reporter can never break core error rendering.
 */
final class ReportingExceptionHandler extends DebugExceptionHandler
{
    private ?CapturedError $captured = null;

    private ?string $reportUrl = null;

    public function handleException(\Throwable $exception): void
    {
        try {
            $this->capture($exception);
        } catch (\Throwable) {
            // Never let the reporter interfere with the actual error being handled.
        }

        parent::handleException($exception);
    }

    protected function getContent(\Throwable $throwable): string
    {
        return $this->banner() . parent::getContent($throwable);
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

    private function banner(): string
    {
        if ($this->captured === null) {
            return '';
        }

        $base = 'margin:0;padding:12px 24px;font-family:sans-serif;font-size:14px;border-bottom:1px solid rgba(0,0,0,.15);';
        if ($this->reportUrl !== null) {
            return sprintf(
                '<div style="%sbackground:#fdf3da;">&#128027; <strong>Report this bug</strong> to <code>%s</code> &mdash; '
                    . '<a href="%s" target="_blank" rel="noopener noreferrer">open a prefilled GitHub issue</a>. '
                    . '<em>Review the prefilled content for sensitive data before submitting.</em></div>',
                $base,
                htmlspecialchars((string) $this->captured->culprit, ENT_QUOTES),
                htmlspecialchars($this->reportUrl, ENT_QUOTES),
            );
        }

        return sprintf(
            '<div style="%sbackground:#f3f4f6;color:#444;">&#128027; nr_bug_reporter: no one-click report &mdash; '
                . 'attributed to <code>%s</code> (%s), tracker: %s.</div>',
            $base,
            htmlspecialchars((string) ($this->captured->culprit ?? 'unknown'), ENT_QUOTES),
            htmlspecialchars($this->captured->confidence, ENT_QUOTES),
            htmlspecialchars($this->captured->trackerStatus, ENT_QUOTES),
        );
    }
}
