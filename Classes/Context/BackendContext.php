<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Context;

/**
 * The backend context at the moment of reporting: which module/route/URL the editor is on, plus a
 * few safe request parameters (page id, edited table/uid) that help a maintainer reproduce.
 */
final class BackendContext
{
    /** @param array<string, string> $params */
    public function __construct(
        public readonly ?string $module,
        public readonly ?string $route,
        public readonly ?string $url,
        public readonly array $params = [],
    ) {}
}
