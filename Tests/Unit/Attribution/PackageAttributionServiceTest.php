<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Tests\Unit\Attribution;

use Netresearch\NrBugReporter\Attribution\AttributionResult;
use Netresearch\NrBugReporter\Attribution\PackageAttributionService;
use Netresearch\NrBugReporter\Attribution\PackageIndex;
use PHPUnit\Framework\TestCase;

/**
 * Self-contained tests for the attribution heuristic, including the two hardening fixes
 * (class-FQCN trait resolution and dispatcher-aware demotion). No TYPO3 runtime required.
 */
final class PackageAttributionServiceTest extends TestCase
{
    private function index(): PackageIndex
    {
        $index = new PackageIndex();
        $index->add('acme/shop', '/app/vendor/acme/shop', 'typo3-cms-extension');
        $index->add('acme/lib', '/app/vendor/acme/lib', 'typo3-cms-extension');
        $index->addPsr4('Acme\\Shop\\', 'acme/shop');
        $index->addPsr4('Acme\\Lib\\', 'acme/lib');
        $index->addPsr4('TYPO3\\CMS\\', 'typo3/cms');

        return $index;
    }

    private function service(): PackageAttributionService
    {
        return new PackageAttributionService($this->index(), 'acme/self');
    }

    public function testThrowSurfacingInCoreAttributesToFirstExtensionFrame(): void
    {
        $result = $this->service()->attribute([
            ['file' => '/app/vendor/typo3/cms-core/Classes/Connection.php', 'class' => 'TYPO3\\CMS\\Core\\Database\\Connection'],
            ['file' => '/app/vendor/acme/shop/Classes/Repository.php', 'class' => 'Acme\\Shop\\Repository'],
        ]);

        self::assertSame('acme/shop', $result->culprit);
        self::assertSame('high', $result->confidence);
    }

    public function testTraitThrowResolvesToConsumingPackageViaClassFqcn(): void
    {
        // Throw site file lives in acme/lib (a trait), but PHP reports the *using* class (acme/shop).
        $result = $this->service()->attribute([
            ['file' => '/app/vendor/acme/lib/Classes/CastTrait.php', 'class' => 'Acme\\Shop\\Model'],
            ['file' => '/app/vendor/acme/shop/Classes/Model.php', 'class' => 'Acme\\Shop\\Model'],
        ]);

        self::assertSame('acme/shop', $result->culprit, 'class-FQCN resolution should blame the trait consumer');
    }

    public function testDispatchedConsumerIsDemotedToLowConfidence(): void
    {
        $result = $this->service()->attribute([
            ['file' => '/app/vendor/acme/shop/Classes/Listener.php', 'class' => 'Acme\\Shop\\Listener'],
            ['file' => '/app/vendor/typo3/cms-core/Classes/EventDispatcher/EventDispatcher.php', 'class' => 'TYPO3\\CMS\\Core\\EventDispatcher\\EventDispatcher'],
        ]);

        self::assertSame('acme/shop', $result->culprit);
        self::assertSame('low', $result->confidence, 'a listener reached via a dispatcher must be low confidence');
    }

    public function testCoreOnlyTraceYieldsCoreSentinel(): void
    {
        $result = $this->service()->attribute([
            ['file' => '/app/vendor/typo3/cms-core/Classes/Utility.php', 'class' => 'TYPO3\\CMS\\Core\\Utility\\GeneralUtility'],
        ]);

        self::assertSame(AttributionResult::CORE, $result->culprit);
    }
}
