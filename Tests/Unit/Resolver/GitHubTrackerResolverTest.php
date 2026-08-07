<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Tests\Unit\Resolver;

use Netresearch\NrBugReporter\Attribution\AttributionResult;
use Netresearch\NrBugReporter\Attribution\PackageIndex;
use Netresearch\NrBugReporter\Resolver\GitHubTrackerResolver;
use PHPUnit\Framework\TestCase;

/**
 * Covers the 4-tier tracker resolution chain and the GitHub-host gate.
 */
final class GitHubTrackerResolverTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/packages';

    private function index(): PackageIndex
    {
        $index = new PackageIndex();
        // Tier 1: composer.json support.issues (GitHub).
        $index->add('acme/gh-issues', self::FIXTURES . '/gh-issues', 'typo3-cms-extension');
        // Tier 2: derive from GitHub homepage (no support.issues).
        $index->add('acme/gh-homepage', self::FIXTURES . '/gh-homepage', 'library');
        // Tier 3: composer.lock/installed.json source url (no on-disk composer.json, SSH form).
        $index->add('acme/lock-only', '/does/not/exist', 'library', 'git@github.com:acme/lock-only.git');
        // Declared but non-GitHub tracker -> must be rejected, never overridden.
        $index->add('acme/gitlab', self::FIXTURES . '/gitlab', 'typo3-cms-extension');

        return $index;
    }

    public function testTier1SupportIssues(): void
    {
        $endpoint = (new GitHubTrackerResolver($this->index()))->resolve('acme/gh-issues');
        self::assertSame('support.issues', $endpoint->status);
        self::assertSame('https://github.com/acme/gh-issues/issues/new', $endpoint->url);
        self::assertTrue($endpoint->isActionable());
    }

    public function testTier2DeriveFromHomepage(): void
    {
        $endpoint = (new GitHubTrackerResolver($this->index()))->resolve('acme/gh-homepage');
        self::assertSame('derived', $endpoint->status);
        self::assertSame('https://github.com/acme/gh-homepage/issues/new', $endpoint->url);
    }

    public function testTier3LockSourceSshUrl(): void
    {
        $endpoint = (new GitHubTrackerResolver($this->index()))->resolve('acme/lock-only');
        self::assertSame('lock-source', $endpoint->status);
        self::assertSame('https://github.com/acme/lock-only/issues/new', $endpoint->url);
    }

    public function testTier4CuratedOverrideMap(): void
    {
        $resolver = new GitHubTrackerResolver($this->index(), ['acme/unknown' => 'https://github.com/acme/unknown']);
        $endpoint = $resolver->resolve('acme/unknown');
        self::assertSame('override-map', $endpoint->status);
        self::assertSame('https://github.com/acme/unknown/issues/new', $endpoint->url);
    }

    public function testNonGitHubTrackerIsRejected(): void
    {
        $endpoint = (new GitHubTrackerResolver($this->index()))->resolve('acme/gitlab');
        self::assertSame('rejected', $endpoint->status);
        self::assertNull($endpoint->url);
        self::assertFalse($endpoint->isActionable());
    }

    public function testCoreSentinelIsNeverGitHub(): void
    {
        $endpoint = (new GitHubTrackerResolver($this->index()))->resolve(AttributionResult::CORE);
        self::assertSame('core', $endpoint->status);
        self::assertFalse($endpoint->isActionable());
    }

    public function testUnknownPackageResolvesToNone(): void
    {
        $endpoint = (new GitHubTrackerResolver($this->index()))->resolve('acme/nope');
        self::assertSame('none', $endpoint->status);
        self::assertFalse($endpoint->isActionable());
    }
}
