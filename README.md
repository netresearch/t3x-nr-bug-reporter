# nr_bug_reporter — attribution spike

A de-risking prototype for the **make-or-break** part of the `nr_bug_reporter` idea: given a PHP
exception in TYPO3, can we reliably decide **which Composer package caused it** and **where its
upstream GitHub issue tracker is**?

This repo is a spike, not the extension. It is framework-agnostic plain PHP (no booted TYPO3) so
the logic can be exercised against a real package index and a corpus of realistic stack traces.

## What it proves / what it does not

- **Proves:** the attribution + resolution logic behaves correctly across the trace shapes in the
  corpus, using a **real** package index (the TYPO3 core install at `../typo3-core-fix-105737`)
  and **real on-disk file paths** for every frame. `bin/run.php` is a designed corpus → it shows
  self-consistency and that the known-hard cases are handled the way we intend.
- **Does not prove:** robustness against trace shapes we did not think of. That is what
  `bin/adversarial.php` (the red-team corpus) is for — there, a mismatch between the heuristic and
  the expert-correct culprit is a **finding**, i.e. a real weakness.

## Run

```bash
php bin/run.php           # designed corpus, pass/fail
php bin/adversarial.php   # red-team corpus, "before" baseline (file-only attribution)
php bin/hardened.php      # red-team corpus through the hardened heuristic + gating ("after")
```

Requires PHP 8.2+ (tested on 8.5) and the sibling `../typo3-core-fix-105737` checkout with
`vendor/` installed (provides the real `installed.php` package index).

## Architecture (3 components)

| Component | Responsibility |
|---|---|
| `src/Attribution/PackageIndex.php` | Build path→package (**longest install-path prefix**) AND namespace→package (**PSR-4 prefix**, for class-FQCN attribution) indexes from `getAllRawData()` + `installed.json`. Expose each package's `composer.json` and VCS source url. |
| `src/Decision/ReportPolicy.php` | Gate whether to actually OFFER a one-click report: withhold for low/core/none confidence, too-short traces, and config/author-error exceptions — so robust resolution cannot amplify an attribution mistake into a harmful public issue. |
| `src/Attribution/PackageAttributionService.php` | Walk the trace innermost→outermost, classify each frame (core / self / infra / extension / library / unknown), pick the most-likely culprit, keep a **ranked alternatives** list for human override. |
| `src/Resolver/GitHubTrackerResolver.php` | Map the culprit package → a prefillable `…/issues/new` URL through a 4-tier fallback chain (below). Everything non-GitHub degrades to a stated reason, never a wrong report. |

### The attribution heuristic

Input is `[getFile(), frame0.file, frame1.file, …]` (innermost→outermost). This matters:
`Throwable::getTrace()` frame `file` is the **caller's** location, and the actual throw site is
only in `getFile()` — so the caller must prepend it.

1. Walk innermost→outermost; classify each frame's owning package.
2. First **extension** (high) or non-infra **library** (medium) frame wins.
3. Else the first **infrastructure** library wins (low confidence; skipped-unless-sole).
4. Else, if only **core** frames were seen → sentinel `CORE` (route to forge / suppress).
5. Else → `null` (compiled template / closure / nothing attributable).

Attribution is deliberately probabilistic; the ranked list is meant to be surfaced so a human
confirms or overrides the winner before anything is filed.

### Resolver fallback chain (4 tiers)

`composer.json`'s `support` block is unreliable in the wild, so resolution falls through tiers:

1. `composer.json` `support.issues` — must be a `github.com/<owner>/<repo>/issues` page.
2. derive from `support.source` / `homepage` when they point at a GitHub repo.
3. **`composer.lock` / `installed.json` VCS `source.url`** — both carry it per package even when
   `support` is empty (e.g. `doctrine/dbal` → `github.com/doctrine/dbal`). **Gated on no declared
   tracker**, so a package that explicitly declares a non-GitHub tracker (private GitLab/Jira) is
   never overridden — this is what keeps a private sitepackage from leaking to a public report.
4. **Maintained curated map** (`fixtures/repo-overrides.php`) — last resort, explicit human decision
   for packages we know are on GitHub but whose metadata does not say so. Never list private packages.

Handles both `https://github.com/owner/repo(.git)` and SSH `git@github.com:owner/repo(.git)`.

## Designed corpus results (`bin/run.php`) — 19/19 (13 traces + project-layout + 5 resolver-chain tiers)

| # | Shape | Culprit (conf) | Tracker |
|---|---|---|---|
| T1 | Extension culprit; TypeError surfaces in core | nr-textdb (high) | github |
| T2 | Core-only trace | CORE (core) | forge / suppress |
| T3 | Extension calls extension | nr-textdb (high), alt nr-llm | github |
| T4 | Infra (doctrine) innermost, extension culprit | nr-textdb (high) | github |
| T5 | Infra+core only (skip-unless-sole) | doctrine/dbal (low) | github (lock-source) |
| T6 | Extension culprit, empty support metadata | nr-image-sitemap (high) | github (lock-source) |
| T7 | GitLab/Jira extension (negative) | nrc-template (high) | rejected (jira host) |
| T8 | Compiled-template + closure frames | nr-textdb (high) | github |
| T9 | Reporter's own frame innermost | nr-textdb (high) — never self-blames | github |
| T10 | Non-infra library culprit (Fluid) | typo3fluid/fluid (medium), alt nr-textdb | github |
| T11 | Unattributable (compiled + closure only) | NONE | — |
| T12 | Clean dogfood (nr-llm throws) | nr-llm (high) | github |
| T13 | Library resolved via **derived** GitHub URL | firebase/php-jwt (medium) | github (derived) |
| PRJ | Project-install layout (sysext as `typo3-cms-framework`) | acme/shop (high) | — |

## Real-world findings surfaced by building this

1. **Monorepo vs project install diverge.** In the core monorepo checkout the root `typo3/cms`
   package **replaces** all `typo3/cms-*` sysext, so they have `install_path = NULL` and no type —
   a stack frame in `typo3/sysext/*` resolves to no package by path. The index/classifier must
   handle this (path-segment + name + type detection), because a normal project install instead
   has sysext as real `typo3/cms-*` packages of type `typo3-cms-framework` under `vendor/`. Both
   layouts are now covered (the `PRJ` check exercises the project layout explicitly).
2. **`getFile()` ≠ `getTrace()[0]`.** The throw site is only in `getFile()`; trace frames carry
   the caller's file. The whole heuristic depends on assembling the sequence correctly.
3. **`support.issues` is unreliable in the wild.** Of the real packages tested: `doctrine/dbal`
   ships **no** `support.issues` and a non-GitHub homepage (unresolvable); `nr-image-sitemap` has
   an **empty** support block; `nrc_template` points at a **bare** `jira.netresearch.de/browse/`
   URL with no project key. The resolver must derive from `source`/`homepage` and degrade loudly.
4. **A masked regex bug.** `deriveIssuesNew()` originally used `#` as the regex delimiter while the
   pattern contained a literal `#` in a character class → `preg_match(): Unknown modifier '?'`.
   The designed corpus passed anyway (no case needed the derive path to reach GitHub). Trace **T13**
   (`firebase/php-jwt`, GitHub homepage, no `support.issues`) was added specifically to force the
   derive path and keep this bug caught.

## Adversarial findings (`bin/adversarial.php`) — 20 traces, 20 mismatches

A red-team (4 attack families) invented real-path traces engineered to break the heuristic. All 20
mismatch the expert culprit (they were built to), and the heuristic behaved **exactly** as the
red-team predicted on 20/20 — i.e. these are real properties of the design, not implementation
quirks. They cluster into categories:

1. **Trait / abstract-base throw-site trap** (INH-1/2/3/5, IND-2, INT-1) — a `throw` written in a
   trait or abstract base is owned (via `getFile()`) by the *defining* package, but the bug belongs
   to the *consuming* subclass. Innermost-wins stops at the trait owner. The single biggest weakness.
2. **Emitter-vs-consumer inversion** (IND-1 event, IND-3 middleware, IND-4 callback) — a core/infra
   dispatcher always sits *between* the innermost consumer (blamed) and the upstream emitter (real
   culprit), so the emitter is structurally unreachable as "first candidate".
3. **Exception re-wrapping erases the root cause** (INT-3) — a service catches an infra/transport
   fault and throws its own exception; the original frames live only in `$previous` (invisible), so
   a healthy extension is blamed for a transient outage. Expert verdict: NONE.
4. **Blame-the-tool** (IND-4, INT-4) — a non-infra library (Fluid) that merely surfaced a consumer's
   misuse wins as innermost candidate → files an OSS bug for a non-bug.
5. **Infra allowlist gaps** (DEG-3) — `firebase/php-jwt` is not on the skip-list, so JWT failures
   (expired/hostile tokens — caller's fault) route to php-jwt's tracker.
6. **Config / template-author errors surfaced from extension code** (DEG-1, DEG-2, INT-2) — an
   extension correctly validates integrator config and throws; the heuristic blames the extension.
7. **var/cache compilation erases the culprit frame** (INT-5) — Fluid compiles the buggy ViewHelper
   call into `var/cache` (skipped), so blame falls through to infra (doctrine/dbal).
8. **No notion of trace completeness** (DEG-4) — a truncated 2-frame production trace gets the same
   high confidence as a full one.
9. **No private/first-party concept** (DEG-5, INH-4, IND-5) — a private sitepackage should never be
   framed as "report upstream."

### ⚠️ Resolution improvements amplify attribution harm

The lock-source (tier 3) and curated-map (tier 4) fallbacks make resolution robust — but they remove
the *accidental* safety net where "missing `support` metadata = no button". After adding them,
INT-5 (`doctrine/dbal`) and DEG-1/DEG-4 (`nr-image-sitemap`) flip from a harmless `(none)` to a
**live, actionable wrong button**. Net: resolution is largely solved; **attribution correctness is
now the binding risk**, and an actionable report must be gated on confidence + human confirmation.

## Hardening pass (`bin/hardened.php`) — harmful reports 20 → 7

Three fixes were applied to the red-team's top findings and measured against the same 20 scenarios
(re-encoded in `fixtures/hardened.php` with the richer signals a real handler captures):

1. **Trait → consumer** — resolve each frame by its runtime `class` FQCN (namespace→package) first,
   file second. PHP reports a trait method's frame with the *using* class, so the consuming package is
   blamed instead of the trait's defining package.
2. **`$previous`-walk** — attribute on the deepest `$previous` (root cause), so a re-wrapped transient
   fault is traced to its infra origin, not the extension wrapper.
3. **Confidence gating** (`ReportPolicy`) — withhold the actionable button for low/core/none confidence,
   traces shorter than 3 frames, and config/author-error exceptions.

| Outcome | n | Meaning |
|---|---|---|
| **FIXED** | 5 | right party + right action (INH-1/2/4/5, IND-2 — all trait cases) |
| **SAFE** | 5 | correctly withheld; expert also wanted no upstream report (INT-3, DEG-1/2/4/5) |
| **MISSED** | 3 | over-withheld — harm-free gating false-negatives (INT-2, INT-4, INT-5) |
| **HARMFUL** | 7 | still a one-click report to the wrong party |

**Harmful one-click wrong reports dropped from 20 to 7.** The flat-file baseline (`bin/adversarial.php`)
is unchanged at 20, so the delta is purely the hardening.

### The 7 residual HARMFUL cases map to next-tier work (out of the top-3 scope)

- **INH-3** — abstract-base throw: PHP reports the *defining* class and the exception trace carries no
  `object`, so the runtime subclass is unrecoverable from the trace alone. Needs reflection on a live
  object (only the handler has it) — a hard, possibly unsolvable limitation. Documented, not faked.
- **IND-1 / IND-3** — emitter-vs-consumer inversion (PSR-14 event / PSR-15 middleware): a core/infra
  dispatcher sits between the blamed consumer and the real upstream emitter.
- **IND-4 / DEG-3 / IND-5** — blame-the-tool / infra-allowlist gaps: non-infra libraries (`typo3fluid/
  fluid`, `firebase/php-jwt`) that merely surfaced a caller's misuse. Needs a wider "caller-misuse-prone"
  classification and emitter analysis.
- **INT-1** — a trait consumed *within* one package while the real culprit is an outer caller; the
  class-FQCN fix correctly resolves the consumer, but the consumer is not the fault owner here.

### Remaining limitations

- The 3 **MISSED** cases are gating false-negatives (a "not configured"/"not set" message suppressed a
  report the expert wanted). The message/class patterns in `ReportPolicy` are a tuning knob.
- The infrastructure skip-list and config-error patterns are hand-maintained; tune against real traces.
- A **human-confirm UI with ranked alternatives** is still required before any filing (the ranked list
  exists in `AttributionResult`); gating reduces but does not eliminate wrong guesses.
- Only Composer-mode installs and GitHub-hosted trackers are in scope (by design).
