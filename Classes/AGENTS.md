<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Classes

<!-- AGENTS-GENERATED:START overview -->
## Overview
Attribution engine + TYPO3 backend integration of EXT:nr_bug_reporter: map an exception trace to the Composer package that most likely caused it, resolve that package's upstream GitHub tracker, and gate whether a one-click prefilled issue is appropriate. Backend-only extension — **no Extbase, no Fluid, no TCA, no database tables**. See `../docs/ARCHITECTURE.md` for the component map.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `Attribution/PackageAttributionService.php` | Core heuristic: walk frames innermost→outermost, class-FQCN-first, skip core/infra, demote dispatcher-reached candidates |
| `Resolver/GitHubTrackerResolver.php` | 4-tier tracker chain (support.issues → source/homepage → lock/installed.json VCS url → curated map), GitHub-host gated |
| `Decision/ReportPolicy.php` | Safety gate: withholds the one-click report for low/core/none confidence, short traces, config/author errors |
| `Report/IssueUrlComposer.php` | Builds the prefilled issue URL, redacts local paths/sensitive data |
| `Error/ReportingExceptionHandler.php` | Extends core `DebugExceptionHandler`; injects the report banner into the error page |
| `Backend/ReportToolbarItem.php` | Proactive toolbar item (v13 Bootstrap dropdown / v14 popover) |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START golden-samples -->
## Golden Samples (follow these patterns)
| Pattern | Reference |
|---------|-----------|
| Pure service (unit-testable, no TYPO3 dependency) | `Attribution/PackageAttributionService.php` |
| Immutable value object | `Resolver/TrackerEndpoint.php`, `Attribution/AttributionResult.php` |
<!-- AGENTS-GENERATED:END golden-samples -->

<!-- AGENTS-GENERATED:START setup -->
## Setup & environment
- Install: `composer install` (repo root; dev-dependency is PHPUnit only)
- PHP: ^8.2 · TYPO3: ^13.4 || ^14.3 (Composer-mode installs)
- DI: `Configuration/Services.yaml` autowires `Classes/*`; `Error\ReportingExceptionHandler` is **excluded** from DI — core instantiates it via `GeneralUtility::makeInstance()`
- The exception handler is activated only via `config/system/additional.php` (`debugExceptionHandler`/`productionExceptionHandler`) — `ext_localconf.php` runs too late (see its header comment)
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Directory structure
```
Attribution/   PackageIndex, PackageAttributionService, AttributionResult   (the engine)
Resolver/      GitHubTrackerResolver, TrackerEndpoint                       (4-tier tracker chain)
Decision/      ReportPolicy                                                 (confidence/config gating)
Service/       PackageIndexProvider                                         (runtime index from Composer)
Capture/       CapturedError, SessionStore                                  (last error + action trail)
Context/       BackendContext, BackendContextCollector                      (module/route/url)
Report/        IssueUrlComposer                                             (prefilled URL + redaction)
Error/         ReportingExceptionHandler                                    (error-page integration)
Backend/       ReportToolbarItem                                            (proactive toolbar)
Middleware/    ActionTrailMiddleware                                        (records recent actions)
EventListener/ BackendAssetLoader                                           (loads the toolbar JS)
```
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Build & tests
| Task | Command |
|------|---------|
| Unit tests | `vendor/bin/phpunit` (root `phpunit.xml.dist`) |
| Single test | `vendor/bin/phpunit --filter <TestName>` |
| Lint | `php -l <file>` |
| Attribution regression | `php bin/run.php`, `php bin/adversarial.php`, `php bin/hardened.php` (needs sibling TYPO3 core checkout; local only) |

There are no composer scripts and no ddev/runTests.sh setup in this repo.
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START code-style -->
## Code style & conventions
- **PSR-12**, `declare(strict_types=1);` in every PHP file, all classes `final`
- Namespace: `Netresearch\NrBugReporter\` (PSR-4 from `Classes/`, see composer.json)
- Constructor DI via `Configuration/Services.yaml` — no `GeneralUtility::makeInstance()` in own services (the exception handler is the deliberate exception, wired by core)
- Keep the core engine (`Attribution/`, `Resolver/`, `Decision/`) free of TYPO3 imports — that is what keeps `Tests/Unit/` portable (`Report/`, `Capture/`, `Context/` already touch TYPO3 APIs)
- No Extbase/Fluid/TCA patterns — this extension has none; do not introduce them for a bug fix
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- **`ReportPolicy` is the blast-radius limiter**: it prevents a wrong attribution from becoming a public GitHub issue on an innocent project. Never loosen its gating without adversarial re-validation (`php bin/hardened.php`).
- **`IssueUrlComposer` redacts** local paths and context before composing the prefilled URL — anything added to the report payload must go through it.
- **`GitHubTrackerResolver` is GitHub-host gated** — never emit a one-click link to an unvalidated host.
- The toolbar renders user-context data into backend HTML — escape all dynamic output (see `ReportToolbarItem`).
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] `vendor/bin/phpunit` green (paste output)
- [ ] Attribution/gating changes: `bin/adversarial.php` + `bin/hardened.php` outcomes re-checked; README result tables updated if they shift
- [ ] New classes: `final`, strict_types, PSR-4 path matches FQCN
- [ ] No TYPO3 runtime dependency introduced into the pure engine classes
- [ ] Works on both TYPO3 13.4 and 14.3 (toolbar markup differs: Bootstrap dropdown vs popover)
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> See **Golden Samples** above; the engine classes are small, final, and constructor-injected — mirror that shape.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- `../README.md` — attribution heuristic, 4-tier resolver chain, adversarial validation results, live-install verification notes
- `../docs/ARCHITECTURE.md` — component map and data flow
- TYPO3 Core API: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/
- Check existing patterns in EXT:core or EXT:backend
- Review root AGENTS.md for project-wide conventions
<!-- AGENTS-GENERATED:END help -->

<!-- AGENTS-GENERATED:START skill-reference -->
## Skill Reference
> For TYPO3 extension standards, TER compliance, and conformance checks:
> **Invoke skill:** `typo3-conformance`
<!-- AGENTS-GENERATED:END skill-reference -->
