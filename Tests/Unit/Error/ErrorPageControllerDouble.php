<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Tests\Unit\Error;

use TYPO3\CMS\Core\Controller\ErrorPageController;

/**
 * Stands in for the core ErrorPageController, whose Fluid template needs a booted TYPO3. Written by
 * hand because the controller is a readonly class, which PHPUnit 10.5 (allowed by composer.json)
 * cannot double.
 */
final readonly class ErrorPageControllerDouble extends ErrorPageController
{
    /**
     * @param \ArrayObject<int, list<mixed>> $calls receives the arguments of each errorAction() call
     */
    public function __construct(
        private \ArrayObject $calls,
    ) {}

    public function errorAction(string $title, string $message, int $errorCode = 0, ?int $httpStatusCode = null): string
    {
        $this->calls->append([$title, $message, $errorCode, $httpStatusCode]);

        return 'production page';
    }
}
