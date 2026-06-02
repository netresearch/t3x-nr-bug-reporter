<?php

declare(strict_types=1);

use Netresearch\NrBugReporter\Error\ReportingExceptionHandler;
use TYPO3\CMS\Core\Core\Environment;

defined('TYPO3') or die();

// In Development context, take over the debug exception handler so an uncaught error gets a
// "Report this bug" action on the error page and is captured for the backend toolbar.
//
// Production capture is opt-in: set the productionExceptionHandler in config/system/additional.php
//   $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] = \Netresearch\NrBugReporter\Error\ReportingExceptionHandler::class;
// (see README) — we do NOT enable it automatically because it changes production error rendering.
if (Environment::getContext()->isDevelopment()) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] = ReportingExceptionHandler::class;
}
