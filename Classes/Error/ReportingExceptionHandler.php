<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Error;

use TYPO3\CMS\Core\Error\DebugExceptionHandler;
use TYPO3\CMS\Core\Error\ProductionExceptionHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extends the core DebugExceptionHandler so an uncaught error is (1) attributed to its originating
 * Composer package, (2) stored in the BE session for the toolbar, and (3) — when a one-click report
 * is appropriate — surfaced as a "Report this bug" banner on the rendered debug page.
 *
 * Meant for $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] only. Registered as
 * productionExceptionHandler, it shows the core production error page instead of the debug page;
 * {@see ReportingProductionExceptionHandler} is the handler for that slot.
 *
 * Instantiated by core via GeneralUtility::makeInstance() with NO arguments (no DI); the parent
 * constructor registers the handler with set_exception_handler(), so we do not declare a constructor.
 * All extra work is wrapped in try/catch so the reporter can never break core error rendering.
 */
final class ReportingExceptionHandler extends DebugExceptionHandler
{
    use CapturesUncaughtErrors;

    public function handleException(\Throwable $exception): void
    {
        $this->captureQuietly($exception);

        parent::handleException($exception);
    }

    public function echoExceptionWeb(\Throwable $exception): void
    {
        if (!self::rendersDebugPage()) {
            // makeInstance() gives the delegate its logger, as core does for the registered handler.
            GeneralUtility::makeInstance(ProductionExceptionHandler::class)->echoExceptionWeb($exception);

            return;
        }

        parent::echoExceptionWeb($exception);
    }

    /**
     * Whether this handler may show the debug page: not when it is registered as the production
     * exception handler, whose page every visitor gets.
     */
    public static function rendersDebugPage(): bool
    {
        return ($GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] ?? null) !== self::class;
    }

    protected function getContent(\Throwable $throwable): string
    {
        return $this->banner() . parent::getContent($throwable);
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
