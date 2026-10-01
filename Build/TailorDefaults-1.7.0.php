<?php

declare(strict_types=1);

/*
 * Snapshot of tailor's own conf/ExcludeFromPackaging.php at version 1.7.0, the
 * release `typo3/tailor:^1` installs.
 *
 * Build/ExcludeFromPackaging.php REPLACES tailor's list rather than merging with
 * it, so it must restate every upstream entry; it does so by merging this file.
 * Copied unchanged from netresearch/t3x-nr-temporal-cache, apart from this
 * comment.
 *
 * When tailor releases a new version, refresh this file from
 * https://github.com/TYPO3/tailor/blob/<tag>/conf/ExcludeFromPackaging.php.
 */

return [
    'directories' => [
        '.build',
        '.ddev',
        '.git',
        '.github',
        '.gitlab',
        '.gitlab-ci',
        '.idea',
        '.phive',
        'bin',
        'build',
        'public',
        'tailor-version-artefact',
        'tailor-version-upload',
        'tests',
        'tools',
        'vendor',
    ],
    'files' => [
        'CODE_OF_CONDUCT.md',
        'DS_Store',
        'Dockerfile',
        'ExtensionBuilder.json',
        'Makefile',
        'bower.json',
        'codeception.yml',
        'composer.lock',
        'crowdin.yaml',
        'docker-compose.yml',
        'dynamicReturnTypeMeta.json',
        'editorconfig',
        'env',
        'eslintignore',
        'eslintrc.json',
        'gitattributes',
        'gitignore',
        'gitlab-ci.yml',
        'gitmodules',
        'gitreview',
        'package-lock.json',
        'package.json',
        'phive.xml',
        'php-cs-fixer.dist.php',
        'php-cs-fixer.php',
        'php_cs',
        'php_cs.php',
        'phpcs.xml',
        'phpcs.xml.dist',
        'phplint.yml',
        'phpstan-baseline.neon',
        'phpstan.neon',
        'phpstan.neon.dist',
        'phpstorm.meta.php',
        'phpunit.xml',
        'phpunit.xml.dist',
        'prettierrc.json',
        'rector.php',
        'scrutinizer.yml',
        'styleci.yml',
        'stylelint.config.js',
        'stylelintrc',
        'travis.yml',
        'tslint.yaml',
        'tslint.yml',
        'typoscript-lint.yaml',
        'typoscript-lint.yml',
        'typoscriptlint.yaml',
        'typoscriptlint.yml',
        'webpack.config.js',
        'webpack.mix.js',
        'yarn.lock',
    ],
];
