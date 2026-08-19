<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — workflows

<!-- AGENTS-GENERATED:START overview -->
## Overview
Thin callers of the shared netresearch reusable workflows. All real CI logic (checkout pins, harden-runner, matrices) is maintained centrally in `netresearch/typo3-ci-workflows` and `netresearch/.github` — these files only select jobs and pass inputs.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `ci.yml` | Calls `typo3-ci-workflows/ci.yml@main`: PHP `8.2–8.5` × TYPO3 `^13.4`/`^14.3`; `run-cgl`/`run-phpstan`/`run-rector` disabled (no such setup in this repo); unit tests via `vendor/bin/phpunit` |
| `checks.yml` | Security/quality jobs (security, gitleaks, zizmor, fuzz, license-check, codeql, scorecard, dependency-review, pr-quality) + the `All security checks` gate. **Byte-identical and drift-enforced across every t3x repo** — only `ci.yml` carries extension-specific settings |
| `harness-verify.yml` | Agent-harness consistency check (`scripts/verify-harness.sh`) via the shared `script-check` reusable |
<!-- AGENTS-GENERATED:END filemap -->

## Workflow files
- 3 workflows, all `uses:`-only thin callers — no inline `run:` steps except the gate job inside `checks.yml`

<!-- AGENTS-GENERATED:START structure -->
## Directory structure
```
.github/
  workflows/
    ci.yml              → build/test matrix (extension-specific inputs live HERE)
    checks.yml          → security jobs + "All security checks" gate (drift-enforced, do not customize)
    harness-verify.yml  → AGENTS.md/docs consistency check
```
No composite actions, no repo-level PR template (org-level `netresearch/.github` provides it), no CODEOWNERS.
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START code-style -->
## Workflow conventions
- **Every new job in `checks.yml` MUST also be added to `gate.needs`** — the gate is the only required context, a job missing there fails silently (see the comment block in the file)
- `uses:` jobs get exactly the reusable's caller contract in their `permissions:` block; top-level `permissions: {}` (checks.yml) or `contents: read` (ci.yml)
- Pin third-party actions to a full commit SHA with a version comment (see harden-runner in the gate job)
- PR-only jobs (`dependency-review`, `pr-quality`) and app-posted checks (CodeQL etc.) are **not requirable** in rulesets — require `All security checks` and `ci / All CI checks` instead (merge-queue safe)
- Extension-specific changes belong in `ci.yml` only; `checks.yml` edits must land in all t3x repos or none
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START patterns -->
## Common patterns

### Thin caller of a shared reusable (the shape used here)
```yaml
jobs:
  ci:
    uses: netresearch/typo3-ci-workflows/.github/workflows/ci.yml@main
    permissions:
      contents: read
    with:
      php-versions: '["8.2","8.3","8.4","8.5"]'
      typo3-versions: '["^13.4","^14.3"]'
```
Before adding an input, read the reusable's `workflow_call.inputs` in `netresearch/typo3-ci-workflows` — do not guess input names.
<!-- AGENTS-GENERATED:END patterns -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- **Minimal permissions**: start from `permissions: {}` and grant per job exactly what the reusable's contract requires
- **Pin actions** to full commit SHA, never mutable tags
- Never use `secrets: inherit`; this repo's workflows pass no secrets
- Do not weaken or remove gate jobs to make a PR green — fix the failing job
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] New `checks.yml` job also listed in `gate.needs`
- [ ] `permissions:` blocks match the called reusable's caller contract
- [ ] Any direct action `uses:` pinned to full SHA
- [ ] Reusable inputs verified against the reusable's `workflow_call` definition
- [ ] `zizmor` (runs in checks.yml) will lint this change — no template-injection patterns
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> `ci.yml` is the reference thin caller; the comment blocks in `checks.yml` document the gate/ruleset rationale — read them before touching required checks.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- The called reusables: https://github.com/netresearch/typo3-ci-workflows and https://github.com/netresearch/.github
- GitHub Actions docs: https://docs.github.com/en/actions
- Workflow syntax: https://docs.github.com/en/actions/reference/workflow-syntax-for-github-actions
- Check the comment blocks inside `checks.yml` — they encode hard-won merge-queue/ruleset facts
<!-- AGENTS-GENERATED:END help -->
