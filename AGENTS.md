<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-08-19 | Last verified: 2026-08-19 -->

# AGENTS.md

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only. Component map: `docs/ARCHITECTURE.md`.

## Commands (verified against composer.json / phpunit.xml.dist / ci.yml)

<!-- AGENTS-GENERATED:START commands -->
| Task | Command | ~Time |
|------|---------|-------|
| Install | `composer install` | ~30s |
| Test (all) | `vendor/bin/phpunit` | ~2s |
| Test (single) | `vendor/bin/phpunit --filter <TestName>` | ~1s |
| Lint | `php -l <file>` (CI lints every PHP file this way) | ~1s |
| Dev harness | `php bin/run.php` / `bin/adversarial.php` / `bin/hardened.php` | ~5s |
<!-- AGENTS-GENERATED:END commands -->

> There are **no composer scripts, no Makefile, no php-cs-fixer/phpstan/rector setup** (CI disables those jobs). PHPUnit config is the root `phpunit.xml.dist` (suite `unit`). The `bin/` harness needs a sibling TYPO3 core checkout — local dev tool, not CI.

## Response Style
- Answer first, elaborate only if needed. No sycophantic openers ("Great question!", "Absolutely!").
- For yes/no or status questions, lead with the answer.
- Skip preamble. Match response length to task complexity.

## Workflow
1. **Before coding**: Read nearest `AGENTS.md` + check Golden Samples for the area you're touching
2. **After each change**: Run the smallest relevant check (lint → single test)
3. **Before committing**: Run full test suite if changes affect >2 files or touch shared code
4. **Before claiming done**: Run verification and **show output as evidence** — never say "try again", "should work now", "tested", "verified", or "all green" without pasted command output in the same turn

## File Map
<!-- AGENTS-GENERATED:START filemap -->
```
Classes/         → PHP classes (PSR-4: Netresearch\NrBugReporter\) — attribution engine + backend UI
Tests/Unit/      → portable PHPUnit tests (plain TestCase, no TYPO3 boot)
Tests/Fixtures/  → composer.json fixtures for the tracker resolver tests
bin/, fixtures/  → CLI dev/regression harness (needs sibling TYPO3 core checkout; not CI)
Configuration/   → TYPO3 wiring: Services.yaml (DI), Icons, JS modules, middlewares
Resources/       → Public/JavaScript/report-toolbar.js + Public/Icons/Extension.svg
```
<!-- AGENTS-GENERATED:END filemap -->

## Golden Samples (follow these patterns)
<!-- AGENTS-GENERATED:START golden-samples -->
| For | Reference | Key patterns |
|-----|-----------|--------------|
| Service | `Classes/Attribution/PackageAttributionService.php` | final class, constructor DI, pure logic |
| Value object | `Classes/Resolver/TrackerEndpoint.php` | small immutable result type |
| Test | `Tests/Unit/Resolver/GitHubTrackerResolverTest.php` | fixture packages, assertion messages |
<!-- AGENTS-GENERATED:END golden-samples -->

## Heuristics (quick decisions)
<!-- AGENTS-GENERATED:START heuristics -->
| When | Do |
|------|-----|
| Adding class | PSR-4 under `Classes/<Component>/`, `final`, `declare(strict_types=1)` |
| Touching attribution/gating | Re-run `vendor/bin/phpunit` AND the `bin/` adversarial harness |
| Merging PRs | Create merge commits |
| Adding dependency | Ask first - we minimize deps |
| Unsure about pattern | Check Golden Samples above |
<!-- AGENTS-GENERATED:END heuristics -->

## Repository Settings
<!-- AGENTS-GENERATED:START repo-settings -->
- **Default branch:** `main`
- **Merge strategy:** merge
- **Signed commits:** required
- **Required checks (rulesets):** `All security checks`, `CodeQL`, `DCO`, `ci / All CI checks`
- **Active rulesets:** require-signed-commits, t3x-baseline, t3x-pull-request
<!-- AGENTS-GENERATED:END repo-settings -->

<!-- AGENTS-GENERATED:START ci-rules -->

<!-- AGENTS-GENERATED:END ci-rules -->

## Boundaries

### Always Do
- Add tests for new code paths
- Use conventional commit format: `type(scope): subject`
- Use **atomic commits** (one logical change per commit); preserve signatures, keep bisection useful
- **Show test output as evidence before claiming work is complete** — never say "try again", "should work now", "tested", "verified", or "all green" without pasted command output
- Before any edit, verify `pwd` resolves inside the intended repo worktree — not `.bare/`, not `~/.claude/skills/…`, not `~/.claude/plugins/cache/…` (those are read-only caches that get clobbered on update)
- For upstream dependency fixes: run **full** test suite, not just affected tests
- Force-push only with `--force-with-lease`
- Follow PSR-12 coding standards and PHP ^8.2 features

### Ask First
- Adding new dependencies
- Modifying CI/CD configuration
- Changing public API signatures
- Weakening `ReportPolicy` gating or the redaction in `IssueUrlComposer` (safety-critical)
- Repo-wide refactoring or rewrites
- Operations that touch >3 repos (produce a dry-run plan first)

### Never Do
- Commit secrets, credentials, or sensitive data
- Modify vendor/ or generated files
- Push directly to main branch — open a PR
- Merge a PR before all review threads are resolved
- Squash commits during merge or rebase unless the user explicitly asked
- Edit installed skill/plugin cache paths (`~/.claude/skills/`, `~/.claude/plugins/cache/`, `**/.bare/**`) — always the source worktree
- Reply to review comments with bare "Addressed" or "Fixed" — cite the resolving commit SHA
- Use `secrets: inherit` in reusable GitHub Actions workflows (pass secrets explicitly)
- Register the exception handler in `ext_localconf.php` — TYPO3 reads it before that file loads; only `config/system/additional.php` works
- Modify core framework files

## Contributing (for AI agents)
- **Comprehension**: Understand the problem before submitting code. Read the linked issue, understand *why* the change is needed, not just *what* to change.
- **Context**: Every PR must explain the trade-offs considered and link to the issue it addresses. Disclose AI assistance if the project requires it.
- **Continuity**: Respond to review feedback. Drive-by PRs without follow-up will be closed.

<!-- AGENTS-GENERATED:START module-boundaries -->

<!-- AGENTS-GENERATED:END module-boundaries -->

## Scoped AGENTS.md (MUST read when working in these directories)
<!-- AGENTS-GENERATED:START scope-index -->
- `./Classes/AGENTS.md` — attribution engine + TYPO3 backend integration conventions
- `./Tests/AGENTS.md` — portable PHPUnit unit tests (no TYPO3 runtime)
- `./.github/workflows/AGENTS.md` — thin callers of the shared netresearch reusable workflows
<!-- AGENTS-GENERATED:END scope-index -->

> **Agents**: When you read or edit files in a listed directory, you **must** load its AGENTS.md first. It contains directory-specific conventions that override this root file.

## When instructions conflict
The nearest `AGENTS.md` wins. Explicit user prompts override files.
- For PHP-specific patterns, follow PSR standards
