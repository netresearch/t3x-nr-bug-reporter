<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Tests

<!-- AGENTS-GENERATED:START overview -->
## Overview
Portable PHPUnit unit tests for the safety-critical pure classes (attribution heuristic, `ReportPolicy` gating, `GitHubTrackerResolver` 4-tier chain). They extend plain `PHPUnit\Framework\TestCase` — **no TYPO3 runtime, no typo3/testing-framework, no database**. The non-portable regression harness lives in `../bin/` + `../fixtures/` (needs a sibling TYPO3 core checkout; local dev only, not CI).
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `Unit/Attribution/PackageAttributionServiceTest.php` | Frame-walking heuristic: class-first resolution, infra skip, dispatcher demotion |
| `Unit/Decision/ReportPolicyTest.php` | Gating rules: confidence thresholds, short traces, config/author errors |
| `Unit/Resolver/GitHubTrackerResolverTest.php` | 4-tier tracker chain against the fixture packages |
| `Fixtures/packages/*/composer.json` | Minimal package manifests (gh-issues, gh-homepage, gitlab) for resolver tests |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START setup -->
## Setup & environment
- `composer install` at repo root (installs PHPUnit ^10.5 || ^11.0 || ^13.0 — the only dev dependency)
- PHPUnit config: root `phpunit.xml.dist`, testsuite `unit` = `Tests/Unit`, bootstrap `vendor/autoload.php`
- `failOnWarning` and `failOnRisky` are enabled — keep test output pristine
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Test Structure
```
Tests/
├── Unit/
│   ├── Attribution/   # PackageAttributionServiceTest
│   ├── Decision/      # ReportPolicyTest
│   └── Resolver/      # GitHubTrackerResolverTest
└── Fixtures/
    └── packages/      # composer.json fixtures per tracker-resolution tier
```
There is no `Tests/Functional/` and no `Tests/Build/` — do not invent them; new functional/E2E infrastructure is an explicit project decision (see README "Status").
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Running Tests
| Type | Command |
|------|---------|
| All unit tests | `vendor/bin/phpunit` |
| Single class | `vendor/bin/phpunit --filter ReportPolicyTest` |
| Single file | `vendor/bin/phpunit Tests/Unit/Decision/ReportPolicyTest.php` |

CI runs exactly `vendor/bin/phpunit` on PHP 8.2–8.5 × TYPO3 ^13.4/^14.3 (see `.github/workflows/ci.yml`). There are no composer test scripts and no test-runner wrapper script.
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START patterns -->
## Patterns to Follow
- Extend `PHPUnit\Framework\TestCase` directly; keep tests free of TYPO3 imports so they stay portable
- One focused test method per trace/package scenario with an explanatory assertion message (current style — no data providers in use)
- Resolver tests read manifests from `Tests/Fixtures/packages/` — add a new fixture package per new resolution tier or edge case
- Attribution scenarios that need a *real* package index belong in the `../bin/` harness, not here
<!-- AGENTS-GENERATED:END patterns -->

<!-- AGENTS-GENERATED:START code-style -->
## Code Style
- Test class name matches source: `MyClass` → `MyClassTest`, `final`, `declare(strict_types=1)`
- One assertion concept per test
- Mock nothing that is pure — the engine classes are constructed directly with test data
- No real HTTP calls, no filesystem writes outside fixtures
<!-- AGENTS-GENERATED:END code-style -->

## Security
- Fixture manifests must stay synthetic — never copy a real project's composer.json with maintainer emails or private repo URLs into `Fixtures/`
- Tests guarding `ReportPolicy` are the regression net for the "no wrong public issue" safety property — never delete or weaken one to get green

<!-- AGENTS-GENERATED:START checklist -->
## PR Checklist
- [ ] `vendor/bin/phpunit` green (paste output)
- [ ] New engine behavior has a test in the matching `Unit/<Component>/` directory
- [ ] Fixtures are minimal and focused
- [ ] No TYPO3 runtime dependency added to any test
- [ ] No warnings/risky tests (config fails on both)
<!-- AGENTS-GENERATED:END checklist -->

## When stuck
- `../README.md` — what each engine component does and which behavior the adversarial corpus pins down
- `../phpunit.xml.dist` — the authoritative suite definition
- Root AGENTS.md for project-wide conventions

<!-- AGENTS-GENERATED:START skill-reference -->
## Skill Reference
> For comprehensive TYPO3 testing guidance including fixtures, mocking, and CI setup:
> **Invoke skill:** `typo3-testing`
<!-- AGENTS-GENERATED:END skill-reference -->
