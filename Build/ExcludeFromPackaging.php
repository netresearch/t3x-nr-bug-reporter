<?php

declare(strict_types=1);

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * Packaging exclude list for `tailor ter:publish`, selected via the
 * TYPO3_EXCLUDE_FROM_PACKAGING environment variable.
 *
 * tailor zips the WORKING DIRECTORY, not the git tree, and applies only this
 * list; it never reads .gitattributes, so `export-ignore` has no effect on the
 * TER artifact. The list REPLACES tailor's own conf/ExcludeFromPackaging.php,
 * so it is composed from TailorDefaults-1.7.0.php, a snapshot of the release
 * `typo3/tailor:^1` installs, plus the entries below. The same layout is used
 * in netresearch/t3x-nr-temporal-cache.
 *
 * Matching, per VersionService::createZipArchiveFromPath() in tailor 1.7.0:
 *   directories  preg_match('/^' . $entry . '/i', $path)  root-anchored,
 *                case-insensitive prefix match; "bin", "build" and "tests"
 *                from the defaults already cover bin/, Build/ and Tests/.
 *   files        preg_match('/' . $entry . '$/i', $filename)  suffix match on
 *                the basename at every depth.
 * Entries are interpolated raw, so keep them plain.
 */

$tailorDefaults = require __DIR__ . '/TailorDefaults-1.7.0.php';

return [
    'directories' => array_merge($tailorDefaults['directories'], [
        // Agent-facing notes (ARCHITECTURE.md, SECURITY-ASSURANCE.md, exec
        // plans). "docs" does not match "Documentation".
        'docs',
        // Inputs of the attribution dev harness in bin/, which the defaults
        // already exclude; no runtime code reads them.
        'fixtures',
        'scripts',
    ]),
    'files' => array_merge($tailorDefaults['files'], [
        // Agent instructions, including the scoped copies in Classes/ and
        // elsewhere.
        'AGENTS.md',
        'CLAUDE.md',
        // Repository tooling with no runtime meaning in an installed extension.
        'renovate.json',
        'bestpractices.json',
    ]),
];
