# EXT:nr_bug_reporter

A TYPO3 backend extension that turns an error into a **prefilled bug report on the originating
package's own upstream GitHub tracker** — plus a proactive "Report an issue" item in the backend
toolbar that captures the current context.

> Status: **beta / MVP.** The attribution engine is unit-tested and the TYPO3 integration is built
> against verified v13.4/v14 APIs, but the backend UI has **not yet been exercised in a booted TYPO3
> instance** (no runtime/E2E verification yet — see [Verification](#verification)).

## What it does

Two entry points, one engine:

1. **Error-triggered (developer-facing).** In *Development* application context, an uncaught exception
   renders the normal TYPO3 debug page with a **"Report this bug"** banner: the error is attributed to
   the third-party Composer package that most likely caused it, and the banner links to a prefilled
   issue on that package's GitHub tracker. If attribution is uncertain or the package is not on GitHub,
   the banner says so instead of filing a wrong report.
2. **Proactive (editor/developer-facing).** A toolbar item (next to clear-cache/user) opens a dropdown
   showing the current **module/URL**, the **last captured error** (with a one-click GitHub link when
   one was resolved), and a short trail of **recent backend actions**. It offers a prefilled report to
   an admin-configured repository, or copy-to-clipboard text when none is configured.

Both share the attribution engine: map a stack frame to its owning package, resolve that package's
upstream GitHub tracker, and gate whether a one-click report is actually appropriate.

## Install

```bash
composer require netresearch/nr-bug-reporter
```

- **Proactive toolbar** appears for any authenticated backend user immediately (registered via DI) —
  no configuration needed.
- **Configure a target repo** (optional) for the proactive report under *Settings → Extension
  Configuration → nr_bug_reporter → `defaultReportRepository`* (a GitHub repo URL). Without it the
  toolbar offers copy-to-clipboard.
- **Error-page "Report this bug" feature — opt-in via `config/system/additional.php`.** It cannot be
  enabled from the extension: TYPO3 reads the exception-handler class during early bootstrap, *before*
  `ext_localconf.php` runs. Add ONE of:
  ```php
  // Development error page:
  $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler']
      = \Netresearch\NrBugReporter\Error\ReportingExceptionHandler::class;
  // Production capture (changes production error rendering — opt in deliberately):
  $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler']
      = \Netresearch\NrBugReporter\Error\ReportingExceptionHandler::class;
  ```

Targets **TYPO3 13.4 LTS + 14.3 LTS**, **PHP 8.2–8.5**, Composer-mode installs.

## How attribution works

Input is the exception's `[getFile(), …getTrace()]` assembled as `{file, class}` frames, innermost→
outermost. The engine:

1. Resolves each frame to its owning package — **class FQCN first** (PSR-4 namespace → package), file
   path second. (Class-first is what makes trait methods resolve to the *consuming* package.)
2. Walks innermost→outermost; the first **extension / non-infra-library** frame wins (extension =
   high, library = medium), skipping TYPO3 core, infrastructure libs (symfony/doctrine/psr/…), and the
   reporter itself. A candidate reached **through a PSR-14/PSR-15 dispatcher** is demoted to low
   confidence (a passive listener/middleware is rarely the real culprit).
3. Falls back to root-cause frames when the exception was **re-wrapped** (`$previous` chain).

Tracker resolution is a 4-tier chain (`composer.json support.issues` → `support.source`/`homepage` →
**`composer.lock`/`installed.json` VCS source url** → **curated map**), GitHub-host gated, handling
both `https://` and SSH (`git@github.com:owner/repo.git`) forms.

A `ReportPolicy` then gates whether to *offer* an actionable button: it withholds for low/core/none
confidence, traces shorter than 3 frames, and config/author-error exceptions — so robust resolution
cannot amplify an attribution mistake into a harmful public issue.

## Validation: the attribution engine

The engine was built spike-first and adversarially tested. The dev harness (CLI, runnable without a
TYPO3 boot — see below) reports:

- **`bin/run.php`** — designed corpus + 4-tier resolver chain: **19/19**.
- **`bin/adversarial.php`** — 20 red-team trace shapes engineered to break attribution (the "before"
  baseline): **20 findings** (the heuristic behaved exactly as an independent red-team predicted, 20/20).
- **`bin/hardened.php`** — the same 20 through the hardened heuristic + gating:

  | Outcome | n | Meaning |
  |---|---|---|
  | FIXED | 5 | right party + right action (the trait cases) |
  | SAFE | 7 | correctly withheld; no upstream report wanted |
  | MISSED | 4 | over-withheld — harm-free gating false-negatives |
  | HARMFUL | 4 | still a one-click report to the wrong party |

  **Harmful one-click reports: 20 → 4.** The 4 residual map to known-hard cases out of current scope:
  abstract-base inheritance (PHP reports the *defining* class — unrecoverable from the trace),
  blame-the-tool for a non-infra library called directly, the infra-allowlist gap (`firebase/php-jwt`),
  and a trait consumed *within* one package where the real culprit is an outer caller.

> The CLI harness (`bin/`, `fixtures/`) is a **local dev tool**: it reads a sibling TYPO3 core checkout
> for a real package index, so it is not portable CI. The portable, self-contained tests live in
> `Tests/Unit/` (PHPUnit, no TYPO3 runtime required).

## Layout

```
Classes/
  Attribution/   PackageIndex, PackageAttributionService, AttributionResult   (the engine)
  Resolver/      GitHubTrackerResolver, TrackerEndpoint                        (4-tier tracker chain)
  Decision/      ReportPolicy                                                  (confidence/config gating)
  Service/       PackageIndexProvider                                          (runtime index from Composer)
  Capture/       CapturedError, SessionStore                                   (last error + action trail)
  Context/       BackendContext, BackendContextCollector                       (module/route/url)
  Report/        IssueUrlComposer                                              (prefilled URL + redaction)
  Error/         ReportingExceptionHandler                                     (error-page integration)
  Backend/       ReportToolbarItem                                             (proactive toolbar)
  Middleware/    ActionTrailMiddleware                                         (records recent actions)
  EventListener/ BackendAssetLoader                                            (loads the toolbar JS)
Configuration/   Services.yaml, JavaScriptModules.php, RequestMiddlewares.php, Icons.php
Resources/       Public/JavaScript/report-toolbar.js, Public/Icons/Extension.svg
Tests/Unit/      attribution + ReportPolicy + GitHubTrackerResolver tests (portable, no TYPO3 boot)
bin/, fixtures/  CLI dev/regression harness (local; needs a sibling TYPO3 core checkout)
.github/         CI: composer validate + lint + PHPUnit on PHP 8.2-8.5
```

## Verification

Verified **live in a real TYPO3 13.4.30 instance** (DDEV, PHP 8.3, Development context):

- ✅ Installs via Composer and **activates cleanly**; the DI container compiles (Services.yaml,
  toolbar autoconfigure, PSR-14 listener, PSR-15 middleware, exception-handler opt-out).
- ✅ Backend loads; the **proactive toolbar item renders** with its icon, and the dropdown shows the
  live context (module, URL), the **recent-actions trail** (the middleware → session works), and the
  copy-report / GitHub-link action — **no console errors**.
- ✅ The **error-page banner is injected** into the debug exception page through the handler, correctly
  gated (a core-only error shows "no one-click report", not a wrong report).
- ✅ Unit tests pass for the safety-critical pure classes (attribution, `ReportPolicy` gating,
  `GitHubTrackerResolver` 4-tier chain); CI workflow runs validate + lint + PHPUnit on PHP 8.2–8.5.
- ✅ Every referenced TYPO3 FQCN/signature was verified against v13.4/v14 core source.

**Found and fixed during the live install:** the exception handler **must** be registered in
`config/system/additional.php` — `ext_localconf.php` runs *after* TYPO3 reads the handler class, so a
registration there is silently ignored. (The proactive toolbar is unaffected — it uses DI.)

## Known limitations

- Attribution is a heuristic; the 4 residual adversarial cases above can still mis-route. The gate and
  the (planned) human-confirm step keep the blast radius small.
- The infra skip-list and config-error message patterns are hand-maintained; tune against real traces.
- Abstract-base inheritance attribution is not recoverable from an exception trace alone.

## License

GPL-2.0-or-later
