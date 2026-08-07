<?php

declare(strict_types=1);

/**
 * Corpus of representative TYPO3 stack-trace shapes for the attribution spike.
 *
 * Each trace's `files` are ordered INNERMOST -> OUTERMOST; files[0] is the throw site
 * (Throwable::getFile()). All non-synthetic paths point at REAL files on disk, so realpath()
 * prefix matching is genuinely exercised — only the call ORDER is modelled to reproduce
 * representative TYPO3 exception shapes. `null` models a frame with no file (closure).
 * The one synthetic path is the compiled-Fluid cache file (classified by path, never read).
 *
 * `expect*` fields encode the intended behaviour, including the deliberately hard/ambiguous
 * cases (T3 innermost-vs-caller, T5 infra-only, T10 library-vs-extension).
 */

$core = '/home/sme/p/typo3-core-fix-105737';
$sys  = $core . '/typo3/sysext';
$ven  = $core . '/vendor';
$llm  = '/home/sme/p/t3x-nr-llm/main';
$tdb  = '/home/sme/p/t3x-nr-textdb';
$img  = '/home/sme/p/t3x-nr-image-sitemap';
$nrc  = '/home/sme/p/nrc_template';
$self = '/home/sme/p/nr-bug-reporter/main';

// Real files (verified to exist).
$coreConnection = $sys . '/core/Classes/Database/Connection.php';
$coreGenUtil    = $sys . '/core/Classes/Utility/GeneralUtility.php';
$extbaseAction  = $sys . '/extbase/Classes/Mvc/Controller/ActionController.php';
$feRequest      = $sys . '/frontend/Classes/Http/RequestHandler.php';
$beDispatcher   = $sys . '/backend/Classes/Http/RouteDispatcher.php';
$doctrineConn   = $ven . '/doctrine/dbal/src/Connection.php';
$doctrineExc    = $ven . '/doctrine/dbal/src/Driver/AbstractSQLServerDriver/Exception/PortWithoutHost.php';
$fluidParser    = $ven . '/typo3fluid/fluid/src/Core/Parser/TemplateParser.php';
$jwtClass       = $ven . '/firebase/php-jwt/src/JWT.php';
$tdbController  = $tdb . '/Classes/Controller/TranslationController.php';
$tdbViewHelper  = $tdb . '/Classes/ViewHelpers/TranslateViewHelper.php';
$llmService     = $llm . '/Classes/Specialized/AbstractSpecializedService.php';
$imgProvider    = $img . '/Classes/Seo/ImagesXmlSitemapDataProvider.php';
$nrcLocalconf   = $nrc . '/ext_localconf.php';
$selfService    = $self . '/Classes/Attribution/PackageAttributionService.php';

// Synthetic: a compiled Fluid template under var/cache (has no owning source package).
$compiledFluid = $core . '/var/cache/code/fluid_template/Layout_action_abc123.php';

return [
    [
        'id' => 'T1',
        'title' => 'Extension culprit; TypeError surfaces in core',
        'files' => [$coreConnection, $tdbController, $extbaseAction, $feRequest],
        'expectCulprit' => 'netresearch/nr-textdb',
        'expectConfidence' => 'high',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'Throw bottoms out in core; the first non-core frame is the buggy extension.',
    ],
    [
        'id' => 'T2',
        'title' => 'Core-only trace (genuine core bug or misconfiguration)',
        'files' => [$coreGenUtil, $beDispatcher, $feRequest],
        'expectCulprit' => 'CORE',
        'expectConfidence' => 'core',
        'expectTracker' => 'core',
        'expectActionable' => false,
        'why' => 'No third-party owner anywhere; must route to forge / suppress, never blame a random extension.',
    ],
    [
        'id' => 'T3',
        'title' => 'Extension calls extension (innermost wins, caller offered as alternative)',
        'files' => [$tdbController, $llmService, $extbaseAction, $feRequest],
        'expectCulprit' => 'netresearch/nr-textdb',
        'expectConfidence' => 'high',
        'expectAlternative' => 'netresearch/nr-llm',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'B threw, A called B: innermost extension wins; the caller A stays in the ranked list for human override.',
    ],
    [
        'id' => 'T4',
        'title' => 'Infra library innermost (doctrine throws); extension is the real culprit',
        'files' => [$doctrineConn, $coreConnection, $tdbController, $extbaseAction],
        'expectCulprit' => 'netresearch/nr-textdb',
        'expectConfidence' => 'high',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'Skip the DBAL + core frames, blame the extension that issued the bad query.',
    ],
    [
        'id' => 'T5',
        'title' => 'Only infrastructure + core frames (skip-unless-sole-candidate)',
        'files' => [$doctrineExc, $doctrineConn, $coreConnection, $coreGenUtil],
        'expectCulprit' => 'doctrine/dbal',
        'expectConfidence' => 'low',
        'expectTracker' => 'lock-source',
        'expectActionable' => true,
        'why' => 'No extension/library present, so the infra lib is the only fallback (low confidence). DBAL ships no GitHub support.issues, but the composer.lock/installed.json source url (github.com/doctrine/dbal) makes it routable.',
    ],
    [
        'id' => 'T6',
        'title' => 'Extension culprit without resolvable tracker metadata',
        'files' => [$imgProvider, $feRequest, $coreGenUtil],
        'expectCulprit' => 'netresearch/nr-image-sitemap',
        'expectConfidence' => 'high',
        'expectTracker' => 'lock-source',
        'expectActionable' => true,
        'why' => 'Empty composer support block, but the project lock source url (git@github.com:netresearch/t3x-nr-image-sitemap) resolves the tracker — demonstrating the lock-source fallback rescuing a package with no support metadata.',
    ],
    [
        'id' => 'T7',
        'title' => 'Extension hosted on GitLab/Jira (negative fixture)',
        'files' => [$nrcLocalconf, $coreGenUtil],
        'expectCulprit' => 'netresearch/nrc-template',
        'expectConfidence' => 'high',
        'expectTracker' => 'rejected',
        'expectActionable' => false,
        'why' => 'support.issues is a bare jira.netresearch.de URL -> the GitHub gate rejects it instead of opening a wrong/empty issue.',
    ],
    [
        'id' => 'T8',
        'title' => 'Compiled-template + closure frames, then extension',
        'files' => [$compiledFluid, null, $tdbViewHelper, $extbaseAction],
        'expectCulprit' => 'netresearch/nr-textdb',
        'expectConfidence' => 'high',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'Skips the var/cache compiled template and the file-less closure frame, still finds the extension.',
    ],
    [
        'id' => 'T9',
        'title' => 'Bug-reporter own frame innermost (must never self-blame)',
        'files' => [$selfService, $tdbViewHelper, $extbaseAction],
        'expectCulprit' => 'netresearch/nr-textdb',
        'expectConfidence' => 'high',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'Frames owned by the reporter itself are skipped, so it never files an issue against its own repo.',
    ],
    [
        'id' => 'T10',
        'title' => 'Non-infra library culprit (Fluid engine), extension as alternative',
        'files' => [$fluidParser, $tdbViewHelper, $extbaseAction, $feRequest],
        'expectCulprit' => 'typo3fluid/fluid',
        'expectConfidence' => 'medium',
        'expectAlternative' => 'netresearch/nr-textdb',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'A non-infra library that throws is the innermost candidate -> wins (medium confidence); the extension is offered as an alternative. Debatable by design; human confirms.',
    ],
    [
        'id' => 'T11',
        'title' => 'Unattributable trace (compiled template + closure only)',
        'files' => [$compiledFluid, null],
        'expectCulprit' => null,
        'expectConfidence' => 'none',
        'expectTracker' => 'none',
        'expectActionable' => false,
        'why' => 'Nothing owns any frame -> no button, rather than guessing.',
    ],
    [
        'id' => 'T12',
        'title' => 'Clean GitHub dogfood happy path (nr-llm throws directly)',
        'files' => [$llmService, $extbaseAction, $feRequest],
        'expectCulprit' => 'netresearch/nr-llm',
        'expectConfidence' => 'high',
        'expectTracker' => 'support.issues',
        'expectActionable' => true,
        'why' => 'The extension throws in its own code -> attributed and routed to its GitHub tracker in one hop.',
    ],
    [
        'id' => 'T13',
        'title' => 'Library culprit resolved via DERIVED GitHub URL (homepage, no support.issues)',
        'files' => [$jwtClass, $tdbViewHelper, $extbaseAction],
        'expectCulprit' => 'firebase/php-jwt',
        'expectConfidence' => 'medium',
        'expectAlternative' => 'netresearch/nr-textdb',
        'expectTracker' => 'derived',
        'expectActionable' => true,
        'why' => 'No support.issues, but homepage is a GitHub repo -> issues/new is derived; this exercises the fallback chain beyond support.issues (and guards the regex-delimiter bug).',
    ],
];
