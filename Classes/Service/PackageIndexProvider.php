<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Service;

use Composer\InstalledVersions;
use Netresearch\NrBugReporter\Attribution\PackageIndex;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Builds (and caches for the request) a PackageIndex from the live Composer runtime, enriched with
 * VCS source urls and PSR-4 namespaces read from the project's vendor/composer/installed.json.
 */
final class PackageIndexProvider implements SingletonInterface
{
    private ?PackageIndex $index = null;

    public function get(): PackageIndex
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $datasets = class_exists(InstalledVersions::class) ? InstalledVersions::getAllRawData() : [];
        $index = PackageIndex::fromGetAllRawData($datasets);

        $installedJson = Environment::getProjectPath() . '/vendor/composer/installed.json';
        $index->loadSourceUrls($installedJson);
        $index->loadNamespaces($installedJson);
        // Map all TYPO3 core/system-extension classes to the core sentinel for class-FQCN attribution.
        $index->addPsr4('TYPO3\\CMS\\', 'typo3/cms');

        return $this->index = $index;
    }
}
