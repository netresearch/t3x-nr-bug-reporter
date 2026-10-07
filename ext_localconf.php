<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

declare(strict_types=1);

defined('TYPO3') or die();

// The proactive backend toolbar item is registered automatically via DI (Configuration/Services.yaml)
// and needs no configuration here.
//
// The error-page "Report this bug" feature CANNOT be enabled from ext_localconf.php: TYPO3 reads the
// exception-handler class names during early bootstrap (Bootstrap::initializeErrorHandling), BEFORE
// ext_localconf.php is loaded. Enable it in config/system/additional.php instead — add one or both:
//
//   // Development error page (debug handler):
//   $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler']
//       = \Netresearch\NrBugReporter\Error\ReportingExceptionHandler::class;
//
//   // Production capture (core production error page; the error is recorded for the toolbar):
//   $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler']
//       = \Netresearch\NrBugReporter\Error\ReportingProductionExceptionHandler::class;
