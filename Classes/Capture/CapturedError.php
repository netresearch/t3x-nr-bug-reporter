<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Capture;

/**
 * Immutable summary of a captured exception, sized for storage in the BE user session and for
 * rendering in the toolbar / on the error page. The full stack trace is deliberately not retained.
 */
final class CapturedError
{
    public function __construct(
        public readonly string $exceptionClass,
        public readonly string $message,
        public readonly int $code,
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $culprit,
        public readonly string $confidence,
        public readonly ?string $trackerUrl,
        public readonly string $trackerStatus,
        public readonly bool $offerReport,
        public readonly int $time,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'exceptionClass' => $this->exceptionClass,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
            'culprit' => $this->culprit,
            'confidence' => $this->confidence,
            'trackerUrl' => $this->trackerUrl,
            'trackerStatus' => $this->trackerStatus,
            'offerReport' => $this->offerReport,
            'time' => $this->time,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['exceptionClass'] ?? ''),
            (string) ($data['message'] ?? ''),
            (int) ($data['code'] ?? 0),
            (string) ($data['file'] ?? ''),
            (int) ($data['line'] ?? 0),
            isset($data['culprit']) ? (string) $data['culprit'] : null,
            (string) ($data['confidence'] ?? 'none'),
            isset($data['trackerUrl']) ? (string) $data['trackerUrl'] : null,
            (string) ($data['trackerStatus'] ?? 'none'),
            (bool) ($data['offerReport'] ?? false),
            (int) ($data['time'] ?? 0),
        );
    }
}
