<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Security assurance

What users of `nr_bug_reporter` can and cannot expect in terms of security, and the argument for it: the data the extension collects, where that data goes, which credentials it uses, who can create a report, the threat model, trust boundaries, the design principles applied and how common weaknesses are countered. Every claim names the file that implements it. Components and data flow: [ARCHITECTURE.md](ARCHITECTURE.md). Vulnerability reporting: [SECURITY.md of the organisation](https://github.com/netresearch/.github/blob/main/SECURITY.md).

The document describes the code on `main`. It covers the proactive toolbar item and the error-page banner with `ReportingExceptionHandler` registered as `debugExceptionHandler`, as the README describes for the Development error page. Statements about dependencies refer to the versions a `composer install` resolved on 2026-09-30: TYPO3 14.3.7, PHPUnit 13.3.6.

## What the extension does, security-wise

The extension builds a link to a prefilled "new issue" form on github.com, and a plain-text report for the clipboard. It never files an issue itself: it has no HTTP client, sends no request to GitHub or any other service, and holds no token. The backend user opens the link in the browser and decides on GitHub, with their own GitHub account, whether to submit the form.

It has no database table, no backend route or AJAX endpoint of its own, and no Extbase, Fluid or TCA code (`Classes/`, `Configuration/`).

## Data collected

| Data | Collected by | Stored where |
|------|--------------|--------------|
| Current backend module identifier, route path and full request URL | `Classes/Context/BackendContextCollector.php` | Not stored; rendered into the toolbar dropdown and the report |
| The GET parameters `id`, `edit`, `table`, `uid`, `action`, `controller`; for array values only the keys | `BackendContextCollector::SAFE_PARAMS` and `fromRequest()` | Not stored |
| Up to 10 recent backend actions: module, route path, HTTP method, time | `Classes/Middleware/ActionTrailMiddleware.php`, `SessionStore::recordAction()` | Backend user session |
| Last uncaught exception: class, message, code, file, line, attributed package, confidence, tracker URL and status, time | `Classes/Error/ReportingExceptionHandler.php`, `Classes/Capture/CapturedError.php` | Backend user session (`SessionStore::recordError()`); the stack trace itself is not kept |
| TYPO3 and PHP version | `Classes/Report/IssueUrlComposer.php` | Not stored |

The session values are written with `BackendUserAuthentication::setAndSaveSessionData()` (`Classes/Capture/SessionStore.php`) and stay there until the next error replaces them or the backend session ends; they are stored unredacted. The extension does not read the user name, e-mail address, IP address, cookies or browser data, and takes no screenshots.

## Where the data goes

- **Toolbar dropdown.** `Classes/Backend/ReportToolbarItem.php` shows the module, URL, last error and action trail of the current session to the logged-in user.
- **GitHub, when the user opens a link.** `IssueUrlComposer::composeForError()` and `composeForContext()` put the report into the `title` and `body` query parameters of a `github.com/<owner>/<repo>/issues/new` URL (`https`, or `http` when a package declares its `support.issues` that way), capped at 6000 characters (`IssueUrlComposer::MAX_URL`). Opening the link transmits these parameters to github.com; the issue is published only when the user submits the form there. The links carry `rel="noopener noreferrer"` (`ReportToolbarItem.php`, `ReportingExceptionHandler.php`), so the backend page URL is not sent as `Referer`.
- **Clipboard.** Without a configured repository the dropdown offers `IssueUrlComposer::plainTextReport()` as text, and `Resources/Public/JavaScript/report-toolbar.js` copies it to the clipboard in the browser; it sends nothing.

Before the report leaves the backend, `IssueUrlComposer::redact()` replaces values that follow `password`, `passwd`, `token`, `secret`, `api_key`, `api-key`, `apikey`, `authorization` or `bearer`, `Bearer` tokens and JWT-like strings, and strips the absolute project path. It is applied to the exception message and file, the request URL and the parameters in both the issue body and the plain-text report (`Tests/Unit/Report/IssueUrlComposerTest.php`). The issue footer and the error-page banner ask the user to review the content before submitting.

## Credentials

The extension uses none. Its only setting is `defaultReportRepository` (`ext_conf_template.txt`), a GitHub repository URL. Filing an issue happens in the user's browser, authenticated by the user's own GitHub session. There is nothing to store or rotate. The repository's workflows pass no secrets to the reusable workflows they call (`.github/workflows/`).

## Who can create a report

- **Toolbar.** `ReportToolbarItem::checkAccess()` shows the item to every authenticated backend user, not only to administrators. The proactive report goes to the repository set in the extension configuration; without one, the user gets the copy-to-clipboard text.
- **Error-page banner.** TYPO3 renders the debug exception page for requests where `SYS.displayErrors` is `1`, or `-1` with the client address matching `SYS.devIPmask` (`Bootstrap::initializeErrorHandling()` of TYPO3). The banner is part of that page and is visible to whoever sees it. It adds the attributed package name, the attribution result and, when a report is offered, the link to a page that already shows the exception and its trace.
- **The issue itself** is created by the person who submits the GitHub form, under their GitHub account and subject to the permissions of the target repository.

## Security expectations

Users can expect:

- **No automatic disclosure.** Nothing leaves the TYPO3 installation unless a user opens a report link or pastes the copied text.
- **Links point to github.com only.** `GitHubTrackerResolver::deriveIssuesNew()` builds every derived link as `https://github.com/%s/%s/issues/new`; a `support.issues` value is used as given only when it matches the anchored pattern in `isGitHubRepoIssues()`, which accepts both `http://github.com/…` and `https://github.com/…`; `TrackerEndpoint::isActionable()` requires the host `github.com`. Trackers on other hosts are reported as not actionable (`GitHubTrackerResolverTest::testNonGitHubTrackerIsRejected`, `testCoreSentinelIsNeverGitHub`).
- **A one-click report is withheld when attribution is weak.** `Classes/Decision/ReportPolicy.php` offers no link for low, core or unknown confidence, for traces shorter than 3 frames and for exceptions that look like configuration or integrator errors (`Tests/Unit/Decision/ReportPolicyTest.php`).
- **The reporter does not break error handling or requests.** The capture in `ReportingExceptionHandler::handleException()` and the recording in `ActionTrailMiddleware::process()` catch every `Throwable` and continue.

Users cannot expect:

- **Complete redaction.** `redact()` matches a fixed list of key words and patterns. Personal data, record content or secrets in other forms in an exception message or URL reach the report unchanged. Review the prefilled text before submitting.
- **Correct attribution in every case.** Attribution is a heuristic; the README lists the known cases that still route a report to the wrong package.
- **Protection of the session data from administrators of the installation.** The last error and the action trail are stored unredacted in the backend session storage of TYPO3.
- **Checks of the target repository.** The extension does not verify that a package owns the GitHub repository named in its `composer.json`.

## Threat model and trust boundaries

| Boundary | Untrusted or semi-trusted input | Control |
|----------|---------------------------------|---------|
| Request → backend | Request URL and query parameters | The full request URL is collected, and the allowlisted parameters (`BackendContextCollector::SAFE_PARAMS`) are listed separately; both are HTML-escaped on output and passed through `redact()` before they enter a report |
| Exception → error page and toolbar | Exception message, file and class, which can contain arbitrary text | Escaped with `htmlspecialchars(..., ENT_QUOTES)` in the banner (`ReportingExceptionHandler::banner()`) and the dropdown (`ReportToolbarItem::esc()`); redacted before it enters a report |
| Installed packages → tracker resolution | `composer.json` and `vendor/composer/installed.json` of installed packages | Trusted to name their own tracker; only github.com links are produced, and `ReportPolicy` gates the offer |
| Backend → github.com | The report, as URL parameters | Sent only when the user opens the link; the user reviews it in the GitHub form |
| Package registry → installation | TYPO3 and PHPUnit | Composer Audit, Dependency Review and the licence check on every pull request (`.github/workflows/checks.yml`); Renovate updates (`renovate.json`) |

Attackers considered: a backend user or visitor who places markup in data that the dropdown or banner renders (countered by escaping), a package whose metadata points reports at an unrelated repository (limited to github.com links behind `ReportPolicy`), and a user who publishes a report without reviewing it (limited by redaction and the review prompts). TYPO3 administrators, system maintainers, the installed packages, the server and its PHP configuration are trusted.

`bin/` and `fixtures/` hold a local regression harness for the attribution engine. They are not loaded by the extension and are excluded from release archives (`.gitattributes`).

## Secure design principles applied

- **Least privilege:** no database table, no outgoing network request, no backend route of its own. Services are private by default (`Configuration/Services.yaml`); only the toolbar item is declared public (`#[Autoconfigure(public: true)]` in `ReportToolbarItem.php`).
- **Fail-safe defaults:** without an actionable GitHub tracker or with weak attribution no link is offered (`ReportPolicy`); without a configured repository the toolbar offers text, not a link; failures in capture or recording are swallowed so core error handling continues.
- **Data minimisation:** the action trail holds module, route, method and time only and is capped at 10 entries (`SessionStore::TRAIL_MAX`); no stack trace is stored (`CapturedError`); array-valued GET parameters are reduced to their keys (`BackendContextCollector::fromRequest()`); the report URL length is capped. The request URL is kept in full and relies on `redact()`.
- **Human in the loop:** the extension prepares a report; a person decides whether to publish it.
- **Economy of mechanism:** every report text is built by one class, `IssueUrlComposer`, and every link target by one resolver, `GitHubTrackerResolver`.

## Countering common weaknesses

| Weakness (CWE / OWASP) | Counter |
|------------------------|---------|
| Cross-site scripting (CWE-79, A03:2021) | All dynamic values in the dropdown and the banner are escaped with `htmlspecialchars(..., ENT_QUOTES)`; the JavaScript only copies the `value` of a textarea |
| Exposure of sensitive information (CWE-200) | `redact()` on the exception message and file, the request URL and the parameters, review prompts, no automatic transmission; residual risk documented above |
| Links to untrusted hosts | Links are built for the host `github.com` only (`GitHubTrackerResolver`, `TrackerEndpoint::isActionable()`) |
| Server-side request forgery (CWE-918) | The extension makes no outgoing requests |
| SQL injection (CWE-89, A03:2021) | `Classes/` issues no database queries |
| Cross-site request forgery (CWE-352) | The extension registers no route or form that changes state; the session writes happen inside requests TYPO3 has already authenticated |
| Hard-coded credentials (CWE-798) | None in the code; Betterleaks scans every pull request (`checks.yml`) |
| Uncontrolled resource consumption (CWE-400) | Trail capped at 10 entries, report URL capped at 6000 characters |
| Vulnerable and outdated components (A06:2021) | Composer Audit and Dependency Review on every pull request (`checks.yml`), Renovate updates (`renovate.json`) |

The unit suite in `Tests/Unit/` backs the resolver, gating and redaction claims and runs on every pull request for PHP 8.2 to 8.5 and TYPO3 13.4 and 14.3 (`.github/workflows/ci.yml`). The repository has no functional or end-to-end tests and no PHPStan configuration; the toolbar and error-page integration were verified manually (README, "Verification"). The checks that run on pull requests are listed under "Governance and policies" in [README.md](../README.md).
