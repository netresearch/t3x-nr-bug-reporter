# Architecture — EXT:nr_bug_reporter

Agent-facing component map. Every path and claim below is verified against the source; when the code and this file disagree, the code wins — update this file in the same PR.

## System Overview

The extension turns a TYPO3 backend error into a prefilled bug report on the originating Composer package's upstream GitHub tracker. Two entry points share one engine: an opt-in exception handler injects a "Report this bug" banner into the debug error page, and a proactive toolbar item offers a report built from captured backend context. The engine attributes a stack trace to a package, resolves that package's tracker, and gates whether offering a one-click public report is safe.

## Components

| Component | Files | Role |
|---|---|---|
| Attribution engine | `Classes/Attribution/` (`PackageIndex`, `PackageAttributionService`, `AttributionResult`) | Map `{file, class}` frames to the owning package: class-FQCN first, path second; innermost-first walk; skips core/infra; demotes dispatcher-reached candidates |
| Tracker resolution | `Classes/Resolver/` (`GitHubTrackerResolver`, `TrackerEndpoint`) | 4-tier chain: `support.issues` → `support.source`/`homepage` → lock/installed.json VCS url → curated map; GitHub-host gated |
| Report gating | `Classes/Decision/ReportPolicy.php` | Withholds the one-click report on low/core/none confidence, traces < 3 frames, config/author-error exceptions |
| Package index provider | `Classes/Service/PackageIndexProvider.php` | Builds the runtime `PackageIndex` from the Composer installation |
| Capture | `Classes/Capture/` (`CapturedError`, `SessionStore`) | Persists the last attributed error and a recent-actions trail in the backend user session |
| Context | `Classes/Context/` (`BackendContext`, `BackendContextCollector`) | Collects current module/route/URL for the proactive report |
| Report composition | `Classes/Report/IssueUrlComposer.php` | Prefilled GitHub issue URL; redacts paths/sensitive values in title and body |
| Error-page integration | `Classes/Error/ReportingExceptionHandler.php` | Extends core `DebugExceptionHandler`; runs the engine, records the error, injects the banner |
| Proactive toolbar | `Classes/Backend/ReportToolbarItem.php`, `Classes/EventListener/BackendAssetLoader.php`, `Resources/Public/JavaScript/report-toolbar.js` | Toolbar dropdown with context, last error, action trail; JS module loaded via `AfterBackendPageRenderEvent` |
| Action trail | `Classes/Middleware/ActionTrailMiddleware.php` | PSR-15 middleware recording recent backend actions into the `SessionStore` |
| Wiring | `Configuration/Services.yaml`, `Configuration/RequestMiddlewares.php`, `Configuration/JavaScriptModules.php`, `Configuration/Icons.php` | DI (handler excluded), middleware registration, ES-module import map, toolbar icon |

## Dependency Rules

No enforced architecture test exists (`Tests/Architecture/` is absent); the following is the observed state to preserve:

- `Attribution/`, `Resolver/`, `Decision/` import no TYPO3 classes — this keeps `Tests/Unit/` runnable without a TYPO3 boot. Do not add TYPO3 imports there.
- `Error/ReportingExceptionHandler` is instantiated by core, not the container: it is excluded from DI in `Services.yaml` and constructs engine objects directly (`new PackageAttributionService(...)`, `GeneralUtility::makeInstance(...)` for the TYPO3-coupled parts).
- Everything else is constructor-injected via `Services.yaml` autowiring.

## Data Flow

**Error-triggered** (opt-in via `config/system/additional.php`, see `ext_localconf.php` header):
`Exception → ReportingExceptionHandler → PackageIndexProvider → PackageAttributionService → GitHubTrackerResolver → ReportPolicy → CapturedError persisted via SessionStore → IssueUrlComposer (if actionable) → banner on the debug error page`

**Proactive toolbar**:
`ActionTrailMiddleware records actions → SessionStore; ReportToolbarItem reads BackendContextCollector + SessionStore, composes via IssueUrlComposer against the admin-configured defaultReportRepository (ext_conf_template.txt) → dropdown with report link or copy-to-clipboard; BackendAssetLoader loads report-toolbar.js`

## Key Decisions

No ADR directory exists. The decision record lives in:

- `README.md` — attribution heuristic design, 4-tier resolver rationale, adversarial validation results (19/19, 20 findings → 4 residual harmful), live-install verification on TYPO3 13.4/14.3
- `ext_localconf.php` header comment — why the exception handler cannot be registered there (core reads it before `ext_localconf.php` loads)
- `Configuration/Services.yaml` comment — why the handler is excluded from DI
- `.github/workflows/checks.yml` comment blocks — gate/ruleset and merge-queue rationale
