<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Resolver;

use Netresearch\NrBugReporter\Attribution\AttributionResult;
use Netresearch\NrBugReporter\Attribution\PackageIndex;

/**
 * Resolves a culprit Composer package to a prefillable GitHub "new issue" endpoint.
 *
 * Resolution order: composer.json support.issues (must be a github.com repo issues page)
 * -> derive from support.source / homepage when they point at a GitHub repo. The MVP gate
 * means anything that does not resolve to github.com degrades to a non-actionable endpoint
 * with a reason (so core, GitLab/Jira, and missing metadata all fail loudly, not silently).
 */
final class GitHubTrackerResolver
{
    /**
     * @param array<string, string> $repoOverrides curated "last resort" map: package name => GitHub
     *        repo/issues URL. Consulted only after auto-resolution fails; an explicit human decision.
     */
    public function __construct(
        private readonly PackageIndex $index,
        private readonly array $repoOverrides = [],
    ) {}

    public function resolve(string $packageName): TrackerEndpoint
    {
        if ($packageName === AttributionResult::CORE) {
            return new TrackerEndpoint(null, null, 'core', 'TYPO3 core -> forge.typo3.org (not a GitHub tracker)');
        }

        $manifest = $this->index->composerManifest($packageName) ?? [];
        $support = is_array($manifest['support'] ?? null) ? $manifest['support'] : [];
        $issues = is_string($support['issues'] ?? null) ? $support['issues'] : '';

        // 1. Explicit support.issues pointing at a GitHub repo issues page.
        if ($issues !== '' && $this->isGitHubRepoIssues($issues)) {
            return new TrackerEndpoint(rtrim($issues, '/') . '/new', 'github.com', 'support.issues', 'composer support.issues');
        }

        // 2. Derive from support.source / homepage when they point at a GitHub repo.
        $sources = [];
        if (is_string($support['source'] ?? null)) {
            $sources['support.source'] = $support['source'];
        }
        if (is_string($support['homepage'] ?? null)) {
            $sources['support.homepage'] = $support['homepage'];
        }
        if (is_string($manifest['homepage'] ?? null)) {
            $sources['homepage'] = $manifest['homepage'];
        }
        foreach ($sources as $origin => $url) {
            $derived = $this->deriveIssuesNew($url);
            if ($derived !== null) {
                return new TrackerEndpoint($derived, 'github.com', 'derived', "derived from {$origin}");
            }
        }

        // 3. composer.lock / installed.json VCS source url. Gated on NO declared tracker, so an
        //    explicitly-declared non-GitHub tracker (private GitLab/Jira) is never overridden.
        if ($issues === '') {
            $source = $this->index->sourceUrl($packageName);
            if (is_string($source)) {
                $derived = $this->deriveIssuesNew($source);
                if ($derived !== null) {
                    return new TrackerEndpoint($derived, 'github.com', 'lock-source', 'derived from composer.lock / installed.json source url');
                }
            }
        }

        // 4. Last resort: a maintained, curated package -> GitHub repo map (explicit human decision).
        if (isset($this->repoOverrides[$packageName])) {
            $derived = $this->deriveIssuesNew($this->repoOverrides[$packageName]);
            if ($derived !== null) {
                return new TrackerEndpoint($derived, 'github.com', 'override-map', 'maintained repo list');
            }
        }

        // 5. Declared-but-non-GitHub tracker -> explicit rejection; otherwise nothing resolvable.
        if ($issues !== '') {
            $host = parse_url($issues, PHP_URL_HOST) ?: 'unknown';

            return new TrackerEndpoint(null, $host, 'rejected', "support.issues host '{$host}' is not a prefillable GitHub tracker");
        }

        return new TrackerEndpoint(null, null, 'none', 'no GitHub issue URL resolvable from composer metadata, lock source, or maintained list');
    }

    private function isGitHubRepoIssues(string $url): bool
    {
        return preg_match('~^https?://github\.com/[^/\s]+/[^/\s]+/issues/?$~', $url) === 1;
    }

    private function deriveIssuesNew(string $url): ?string
    {
        // Handles both https://github.com/owner/repo(.git) and SSH git@github.com:owner/repo(.git).
        if (preg_match('~github\.com[/:]([^/\s:]+)/([^/\s#?]+)~', $url, $matches) !== 1) {
            return null;
        }

        $repo = (string) preg_replace('~\.git$~', '', $matches[2]);

        return sprintf('https://github.com/%s/%s/issues/new', $matches[1], $repo);
    }
}
