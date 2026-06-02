<?php

declare(strict_types=1);

/**
 * Shared bootstrap: registers the PSR-4 autoloader and returns a PackageIndex built from the
 * REAL TYPO3 core install (typo3-core-fix-105737), augmented with locally-available extensions
 * as if they were installed in a project (real paths + real composer.json).
 *
 * @return \Netresearch\NrBugReporter\Attribution\PackageIndex
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Netresearch\\NrBugReporter\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../Classes/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$coreInstalled = '/home/sme/p/typo3-core-fix-105737/vendor/composer/installed.php';
if (!is_file($coreInstalled)) {
    fwrite(STDERR, "FATAL: core installed.php not found at {$coreInstalled}\n");
    exit(2);
}

/** @var array<string, mixed> $installed */
$installed = require $coreInstalled;
$index = \Netresearch\NrBugReporter\Attribution\PackageIndex::fromGetAllRawData([$installed]);

// VCS source urls for every core package (real data: installed.json carries source.url even when
// a package's composer.json support block is empty — e.g. doctrine/dbal -> github.com/doctrine/dbal).
$index->loadSourceUrls('/home/sme/p/typo3-core-fix-105737/vendor/composer/installed.json');

// Augment with locally-available extensions, as if installed in a project. The source urls are the
// real git remotes a project's composer.lock would record (SSH form for the NR repos).
$index->add('netresearch/nr-llm', '/home/sme/p/t3x-nr-llm/main', 'typo3-cms-extension', 'git@github.com:netresearch/t3x-nr-llm.git');
$index->add('netresearch/nr-textdb', '/home/sme/p/t3x-nr-textdb', 'typo3-cms-extension', 'git@github.com:netresearch/t3x-nr-textdb.git');
$index->add('netresearch/nr-image-sitemap', '/home/sme/p/t3x-nr-image-sitemap', 'typo3-cms-extension', 'git@github.com:netresearch/t3x-nr-image-sitemap.git');
$index->add('netresearch/nrc-template', '/home/sme/p/nrc_template', 'typo3-cms-extension', 'git@git.netresearch.de:netresearch/nrc_template.git');
$index->add('netresearch/nr-bug-reporter', '/home/sme/p/nr-bug-reporter/main', 'typo3-cms-extension', 'git@github.com:netresearch/t3x-nr-bug-reporter.git');

// PSR-4 namespace => package, for class-FQCN attribution (the trait/inheritance fix). Core packages
// come from installed.json; the augmented extensions and the TYPO3\CMS\ core prefix are added here.
$index->loadNamespaces('/home/sme/p/typo3-core-fix-105737/vendor/composer/installed.json');
$index->addPsr4('Netresearch\\NrLlm\\', 'netresearch/nr-llm');
$index->addPsr4('Netresearch\\NrTextdb\\', 'netresearch/nr-textdb');
$index->addPsr4('Netresearch\\NrImageSitemap\\', 'netresearch/nr-image-sitemap');
$index->addPsr4('Netresearch\\NrcTemplate\\', 'netresearch/nrc-template');
$index->addPsr4('Netresearch\\NrBugReporter\\', 'netresearch/nr-bug-reporter');
$index->addPsr4('TYPO3\\CMS\\', 'typo3/cms');

return $index;
