<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `ReportingProductionExceptionHandler` for
  `$GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler']`. It
  extends the core `ProductionExceptionHandler`: visitors get the core
  production error page, and the error is recorded for the toolbar of a
  logged-in backend user.

### Fixed

- `ReportingExceptionHandler` renders the core production error page, not
  the debug page, when it is registered as `productionExceptionHandler`.
  The README and the comment in `ext_localconf.php` now name
  `ReportingProductionExceptionHandler` for that setting; installations
  that followed the README of 0.1.0 should switch to it.

## [0.1.0] - 2026-09-30

First release, for TYPO3 13.4 LTS and 14.3 LTS on PHP 8.2 to 8.5, installed
with Composer. The extension is in beta: it has unit tests, but no functional
or end-to-end tests yet.

### Added

- Attribution engine: maps each frame of an exception's stack trace to the
  Composer package that owns it (class name first, file path second), picks
  the innermost extension or non-infrastructure library frame, and resolves
  that package's upstream GitHub issue tracker. It offers a one-click report
  only when the attribution is confident and the tracker is on GitHub.
- Backend toolbar item "Report an issue" for every authenticated backend
  user. It shows the current module and URL, the last captured error and a
  short trail of recent backend actions, and opens a prefilled issue on the
  repository set in the extension configuration `defaultReportRepository`,
  or offers the text for the clipboard when none is set.
- Exception handler `ReportingExceptionHandler` that adds a "Report this bug"
  banner with a prefilled issue link to the TYPO3 error page. It is opt-in and
  must be registered in `config/system/additional.php`, because TYPO3 reads
  the exception handler class before `ext_localconf.php` runs; the README
  shows the setting.

[Unreleased]: https://github.com/netresearch/t3x-nr-bug-reporter/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/netresearch/t3x-nr-bug-reporter/releases/tag/v0.1.0
