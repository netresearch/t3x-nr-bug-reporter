<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Error;

use TYPO3\CMS\Core\Error\ProductionExceptionHandler;

/**
 * Extends the core ProductionExceptionHandler so an uncaught error in production is attributed and
 * stored in the BE session, where the toolbar shows it with its report link. The page itself stays
 * the core production error page: no banner, no trace.
 *
 * Meant for $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler']. Instantiated by core via
 * GeneralUtility::makeInstance() with NO arguments (no DI); the parent constructor registers it.
 */
final class ReportingProductionExceptionHandler extends ProductionExceptionHandler
{
    use CapturesUncaughtErrors;

    public function handleException(\Throwable $exception): void
    {
        $this->captureQuietly($exception);

        parent::handleException($exception);
    }
}
