<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Attribution;

/**
 * Outcome of attributing a stack trace to a culprit package.
 *
 * $culprit is a Composer package name, the sentinel self::CORE, or null (no owner found).
 * The result is always a *guess* — the ranked candidate list is meant to be surfaced in the UI
 * so a human can override the winner before anything is filed.
 */
final class AttributionResult
{
    public const CORE = 'CORE';

    /**
     * @param list<array{package:?string, type:?string, file:string, class:string}> $ranked
     */
    public function __construct(
        public readonly ?string $culprit,
        public readonly string $confidence, // high|medium|low|core|none
        public readonly array $ranked,
        public readonly string $reason,
    ) {}

    public function isCore(): bool
    {
        return $this->culprit === self::CORE;
    }

    /**
     * Distinct candidate package names, best-first — the override list for the UI.
     *
     * @return list<string>
     */
    public function alternatives(): array
    {
        $names = [];
        foreach ($this->ranked as $row) {
            if ($row['package'] !== null && !in_array($row['package'], $names, true)) {
                $names[] = $row['package'];
            }
        }

        return $names;
    }
}
