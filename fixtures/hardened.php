<?php

declare(strict_types=1);

/**
 * "After" corpus for the hardened heuristic — the 20 red-team scenarios re-encoded with the richer
 * signals a real handler can capture from a Throwable:
 *   - each frame may carry the runtime `class` FQCN (getTrace()[i]['class']) alongside its `file`;
 *   - `rootCause` carries the deepest $previous frames (for the re-wrap fix);
 *   - `exceptionClass` / `message` drive confidence gating.
 *
 * Frame `class` values reflect REAL PHP semantics verified on PHP 8.5: a trait method's frame reports
 * the *using* class (consumer), while an inherited method's frame reports the *defining* class (so the
 * abstract-base case is intentionally left as the defining package — an honest, unfixable-from-trace
 * limitation). frames are INNERMOST -> OUTERMOST; frames[0] = throw site.
 *
 * `expertCulprit` / `expertActionable` = the correct outcome. bin/hardened.php compares the hardened
 * result to these and against the flat-file "before" behaviour.
 */

$core = '/home/sme/p/typo3-core-fix-105737';
$sys  = $core . '/typo3/sysext';
$ven  = $core . '/vendor';
$llm  = '/home/sme/p/t3x-nr-llm/main';
$tdb  = '/home/sme/p/t3x-nr-textdb';
$img  = '/home/sme/p/t3x-nr-image-sitemap';
$nrc  = '/home/sme/p/nrc_template';

$safeCastTrait   = $llm . '/Classes/Utility/SafeCastTrait.php';
$respParserTrait = $llm . '/Classes/Provider/ResponseParserTrait.php';
$abstractSvc     = $llm . '/Classes/Specialized/AbstractSpecializedService.php';
$tdbTranslation  = $tdb . '/Classes/Domain/Model/Translation.php';
$tdbController   = $tdb . '/Classes/Controller/TranslationController.php';
$tdbViewHelper   = $tdb . '/Classes/ViewHelpers/TranslateViewHelper.php';
$imgProvider     = $img . '/Classes/Seo/ImagesXmlSitemapDataProvider.php';
$nrcLocalconf    = $nrc . '/ext_localconf.php';

$extbaseAction   = $sys . '/extbase/Classes/Mvc/Controller/ActionController.php';
$feRequest       = $sys . '/frontend/Classes/Http/RequestHandler.php';
$beDispatcher    = $sys . '/backend/Classes/Http/RouteDispatcher.php';
$coreEvent       = $sys . '/core/Classes/EventDispatcher/EventDispatcher.php';
$coreMiddleware  = $sys . '/core/Classes/Http/MiddlewareDispatcher.php';
$coreGenUtil     = $sys . '/core/Classes/Utility/GeneralUtility.php';
$coreConnection  = $sys . '/core/Classes/Database/Connection.php';
$symfonyEvent    = $ven . '/symfony/event-dispatcher/EventDispatcher.php';
$doctrineConn    = $ven . '/doctrine/dbal/src/Connection.php';
$psrLogger       = $ven . '/psr/log/src/AbstractLogger.php';
$fluidParser     = $ven . '/typo3fluid/fluid/src/Core/Parser/TemplateParser.php';
$jwt             = $ven . '/firebase/php-jwt/src/JWT.php';
$cacheFluid      = $core . '/var/cache/code/fluid_template/Layout_action_abc123.php';

$fr = static fn (string $file, ?string $class = null): array => ['file' => $file, 'class' => $class];

return [
    // ---- Trait throw-site trap: class-FQCN resolution should now pick the consuming package ----
    ['id' => 'INH-1', 'title' => 'Trait throw, consumer = nr-textdb',
     'exceptionClass' => 'RuntimeException', 'message' => 'Cannot cast value to int',
     'frames' => [$fr($safeCastTrait, 'Netresearch\\NrTextdb\\Domain\\Model\\Translation'), $tdbTranslation, $tdbController, $extbaseAction, $beDispatcher],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'INH-2', 'title' => 'Protected trait method, consumer = nr-textdb',
     'exceptionClass' => 'InvalidArgumentException', 'message' => 'Expected array, got string',
     'frames' => [$fr($respParserTrait, 'Netresearch\\NrTextdb\\Controller\\TranslationController'), $tdbController, $extbaseAction, $coreMiddleware, $beDispatcher],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'INH-3', 'title' => 'Abstract-base throw (PHP reports DEFINING class) — not fixable from trace',
     'exceptionClass' => 'LogicException', 'message' => 'Invalid state in lifecycle hook',
     'frames' => [$fr($abstractSvc, 'Netresearch\\NrLlm\\Specialized\\AbstractSpecializedService'), $tdbController, $extbaseAction, $beDispatcher],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'INH-4', 'title' => 'Private (GitLab) sitepackage uses nr-llm trait',
     'exceptionClass' => 'RuntimeException', 'message' => 'bad cast during boot',
     'frames' => [$fr($safeCastTrait, 'Netresearch\\NrcTemplate\\Service\\BootService'), $nrcLocalconf, $coreGenUtil, $feRequest],
     'expertCulprit' => 'netresearch/nrc-template', 'expertActionable' => false],

    ['id' => 'INH-5', 'title' => 'Trait + compiled-Fluid frame; consumer = nr-textdb ViewHelper',
     'exceptionClass' => 'RuntimeException', 'message' => 'cast failed',
     'frames' => [$fr($safeCastTrait, 'Netresearch\\NrTextdb\\ViewHelpers\\TranslateViewHelper'), $cacheFluid, $tdbViewHelper, $fluidParser, $feRequest],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    // ---- Indirection layers ----
    ['id' => 'IND-1', 'title' => 'PSR-14 listener (nr-llm) blamed for core-built bad payload',
     'exceptionClass' => 'TypeError', 'message' => 'Argument must be string, null given',
     'frames' => [$fr($abstractSvc, 'Netresearch\\NrLlm\\EventListener\\PayloadListener'), $coreEvent, $coreGenUtil, $feRequest],
     'expertCulprit' => 'CORE', 'expertActionable' => false],

    ['id' => 'IND-2', 'title' => 'Trait in event flow, consumer = nr-textdb',
     'exceptionClass' => 'InvalidArgumentException', 'message' => 'malformed payload',
     'frames' => [$fr($respParserTrait, 'Netresearch\\NrTextdb\\Controller\\TranslationController'), $tdbController, $extbaseAction, $beDispatcher],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'IND-3', 'title' => 'PSR-15 inner middleware (nr-image-sitemap) vs outer mutator (nr-textdb)',
     'exceptionClass' => 'RuntimeException', 'message' => 'invalid locale attribute',
     'frames' => [$fr($imgProvider, 'Netresearch\\NrImageSitemap\\Middleware\\SitemapMiddleware'), $coreMiddleware, $tdbController, $coreMiddleware, $feRequest],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'IND-4', 'title' => 'Callback into Fluid library; real misuser = nr-llm',
     'exceptionClass' => 'TYPO3Fluid\\Fluid\\Core\\Parser\\Exception', 'message' => 'Parse error in template',
     'frames' => [$fr($fluidParser, 'TYPO3Fluid\\Fluid\\Core\\Parser\\TemplateParser'), null, $fr($safeCastTrait, 'Netresearch\\NrLlm\\Provider\\OpenAiProvider'), $coreEvent, $ven . '/symfony/dependency-injection/Container.php'],
     'expertCulprit' => 'netresearch/nr-llm', 'expertActionable' => true],

    ['id' => 'IND-5', 'title' => 'php-jwt blamed; emitter = private nrc-template',
     'exceptionClass' => 'Firebase\\JWT\\SignatureInvalidException', 'message' => 'Signature verification failed',
     'frames' => [$fr($jwt, 'Firebase\\JWT\\JWT'), $symfonyEvent, $fr($nrcLocalconf, 'Netresearch\\NrcTemplate\\Auth\\TokenEmitter'), $coreMiddleware],
     'expertCulprit' => 'netresearch/nrc-template', 'expertActionable' => false],

    // ---- Multi-extension interplay ----
    ['id' => 'INT-1', 'title' => 'Trait consumed within nr-llm; real culprit = outer nr-textdb caller',
     'exceptionClass' => 'InvalidArgumentException', 'message' => 'Expected JSON, got HTML',
     'frames' => [$fr($respParserTrait, 'Netresearch\\NrLlm\\Specialized\\AbstractSpecializedService'), $abstractSvc, $tdbController, $beDispatcher],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'INT-2', 'title' => 'A->B: nr-textdb hard-invokes unconfigured nr-llm service (throws by design)',
     'exceptionClass' => 'Netresearch\\NrLlm\\Exception\\ServiceUnavailableException', 'message' => 'LLM service is not configured',
     'frames' => [$fr($abstractSvc, 'Netresearch\\NrLlm\\Specialized\\AbstractSpecializedService'), $tdbViewHelper, $cacheFluid, $feRequest],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'INT-3', 'title' => 'Re-wrapped transient transport fault (root cause = infra)',
     'exceptionClass' => 'Netresearch\\NrLlm\\Exception\\ServiceUnavailableException', 'message' => 'Upstream request failed',
     'frames' => [$fr($abstractSvc, 'Netresearch\\NrLlm\\Specialized\\AbstractSpecializedService'), $psrLogger, $coreEvent, $feRequest],
     'rootCause' => [$psrLogger, $coreGenUtil],
     'expertCulprit' => 'NONE', 'expertActionable' => false],

    ['id' => 'INT-4', 'title' => 'Fluid relays a ViewHelper author error; culprit = nr-textdb',
     'exceptionClass' => 'TYPO3Fluid\\Fluid\\Core\\Parser\\Exception', 'message' => 'Required argument "key" not set',
     'frames' => [$fr($fluidParser, 'TYPO3Fluid\\Fluid\\Core\\Parser\\TemplateParser'), $tdbViewHelper, $feRequest],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    ['id' => 'INT-5', 'title' => 'Extension hidden behind compiled Fluid; doctrine absorbs blame',
     'exceptionClass' => 'Doctrine\\DBAL\\Exception\\SyntaxErrorException', 'message' => 'SQL syntax error near WHERE',
     'frames' => [$fr($doctrineConn, 'Doctrine\\DBAL\\Connection'), $coreConnection, $cacheFluid, $feRequest],
     'expertCulprit' => 'netresearch/nr-textdb', 'expertActionable' => true],

    // ---- Degenerate / pathological ----
    ['id' => 'DEG-1', 'title' => 'TypoScript misconfig (MissingConfigurationException) from extension code',
     'exceptionClass' => 'Netresearch\\NrImageSitemap\\Exception\\MissingConfigurationException', 'message' => 'No configuration found for sitemap',
     'frames' => [$fr($imgProvider, 'Netresearch\\NrImageSitemap\\Seo\\ImagesXmlSitemapDataProvider'), $feRequest, $coreMiddleware],
     'expertCulprit' => 'NONE', 'expertActionable' => false],

    ['id' => 'DEG-2', 'title' => 'Fluid/controller author error ("please set a component")',
     'exceptionClass' => 'RuntimeException', 'message' => 'Please set a component in your controller for the translate ViewHelper',
     'frames' => [$fr($tdbViewHelper, 'Netresearch\\NrTextdb\\ViewHelpers\\TranslateViewHelper'), $cacheFluid, $extbaseAction, $feRequest],
     'expertCulprit' => 'NONE', 'expertActionable' => false],

    ['id' => 'DEG-3', 'title' => 'JWT verification failure (caller misuse) -> php-jwt',
     'exceptionClass' => 'Firebase\\JWT\\ExpiredException', 'message' => 'Expired token',
     'frames' => [$fr($jwt, 'Firebase\\JWT\\JWT'), $coreGenUtil, $beDispatcher],
     'expertCulprit' => 'CORE', 'expertActionable' => false],

    ['id' => 'DEG-4', 'title' => 'Truncated production trace (throw + 1 frame)',
     'exceptionClass' => 'TypeError', 'message' => 'Call to a member function getRootPageId() on null',
     'frames' => [$fr($imgProvider, 'Netresearch\\NrImageSitemap\\Seo\\ImagesXmlSitemapDataProvider'), $feRequest],
     'expertCulprit' => 'CORE', 'expertActionable' => false],

    ['id' => 'DEG-5', 'title' => 'Private client sitepackage bootstrap failure',
     'exceptionClass' => 'RuntimeException', 'message' => 'Broken TSconfig include path',
     'frames' => [$fr($nrcLocalconf, 'Netresearch\\NrcTemplate\\Bootstrap\\Registrar'), $coreGenUtil, null],
     'expertCulprit' => 'NONE', 'expertActionable' => false],
];
