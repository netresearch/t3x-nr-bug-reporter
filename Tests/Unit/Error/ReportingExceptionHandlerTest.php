<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Tests\Unit\Error;

use Netresearch\NrBugReporter\Error\ReportingExceptionHandler;
use Netresearch\NrBugReporter\Error\ReportingProductionExceptionHandler;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Controller\ErrorPageController;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Error\DebugExceptionHandler;
use TYPO3\CMS\Core\Error\ProductionExceptionHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Which page the handlers render. The production exception handler's page reaches every visitor,
 * so it must be the core production error page: a generic title and message, no exception message,
 * file or trace. The core ErrorPageController is replaced by ErrorPageControllerDouble, which records
 * its arguments, because rendering its Fluid template needs a booted TYPO3.
 */
final class ReportingExceptionHandlerTest extends TestCase
{
    private const DETAIL = 'Detail that only the debug page shows';

    /** @var array<string, mixed>|null */
    private ?array $systemConfiguration = null;

    protected function setUp(): void
    {
        $this->systemConfiguration = $GLOBALS['TYPO3_CONF_VARS']['SYS'] ?? null;
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            '/var/www/project',
            '/var/www/project/public',
            '/var/www/project/var',
            '/var/www/project/config',
            '/var/www/project/vendor/bin/phpunit',
            'UNIX',
        );
    }

    protected function tearDown(): void
    {
        if ($this->systemConfiguration === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS'] = $this->systemConfiguration;
        }
        GeneralUtility::purgeInstances();
    }

    public function testTheProductionHandlerIsTheCoreProductionHandler(): void
    {
        self::assertTrue(is_subclass_of(ReportingProductionExceptionHandler::class, ProductionExceptionHandler::class));
        self::assertFalse(is_subclass_of(ReportingProductionExceptionHandler::class, DebugExceptionHandler::class));
    }

    public function testRegisteredAsDebugHandlerItRendersTheDebugPage(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] = ReportingExceptionHandler::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] = ProductionExceptionHandler::class;

        self::assertTrue(ReportingExceptionHandler::rendersDebugPage());
    }

    public function testRegisteredAsProductionHandlerItRendersTheProductionPage(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] = DebugExceptionHandler::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] = ReportingExceptionHandler::class;
        $errorPage = $this->errorPageDouble();

        self::assertFalse(ReportingExceptionHandler::rendersDebugPage());

        $output = $this->renderWeb(new ReportingExceptionHandler());

        self::assertSame('production page', $output);
        self::assertCount(1, $errorPage);
        self::assertStringNotContainsString(self::DETAIL, implode(' ', array_map('strval', $errorPage[0])));
    }

    private function renderWeb(DebugExceptionHandler $handler): string
    {
        ob_start();
        try {
            $handler->echoExceptionWeb(new \RuntimeException(self::DETAIL, 1790300001));
        } finally {
            $output = (string) ob_get_clean();
            // Each core handler registers itself with set_exception_handler() when constructed.
            restore_exception_handler();
            restore_exception_handler();
        }

        return $output;
    }

    /**
     * @return \ArrayObject<int, list<mixed>> the arguments of each errorAction() call
     */
    private function errorPageDouble(): \ArrayObject
    {
        $calls = new \ArrayObject();
        GeneralUtility::addInstance(ErrorPageController::class, new ErrorPageControllerDouble($calls));

        return $calls;
    }
}
