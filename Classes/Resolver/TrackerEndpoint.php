<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Resolver;

/**
 * Result of resolving a culprit package to an upstream issue tracker.
 *
 * Only github.com endpoints are actionable in the MVP; every other outcome (GitLab/Jira,
 * missing metadata, core) is represented here with a stated reason so the UI can explain
 * *why* it cannot offer a one-click report instead of silently doing nothing.
 */
final class TrackerEndpoint
{
    /**
     * @param string $status one of: support.issues | derived | rejected | core | none
     */
    public function __construct(
        public readonly ?string $url,
        public readonly ?string $host,
        public readonly string $status,
        public readonly string $reason,
    ) {}

    public function isActionable(): bool
    {
        return $this->url !== null && $this->host === 'github.com';
    }
}
