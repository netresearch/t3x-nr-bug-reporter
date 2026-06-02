<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Report;

use Netresearch\NrBugReporter\Capture\CapturedError;
use Netresearch\NrBugReporter\Context\BackendContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Builds a prefilled GitHub "new issue" URL (?title=&body=&labels=bug) from a captured error and/or
 * the backend context. Redacts obvious secrets and absolute project paths, and caps the URL length
 * (long prefilled bodies otherwise hit HTTP 414). Also produces a plain-text report for clipboard.
 */
final class IssueUrlComposer
{
    private const MAX_URL = 6000;

    public function __construct(
        private readonly Typo3Version $typo3Version,
    ) {}

    public function composeForError(string $issuesNewUrl, CapturedError $error, ?BackendContext $context): string
    {
        $title = $error->exceptionClass . ': ' . $error->message;
        $body = "### Error\n\n```\n"
            . $error->exceptionClass . ': ' . $this->redact($error->message) . "\n"
            . $this->redact($error->file) . ':' . $error->line . "\n```\n\n"
            . '**Attributed package:** ' . ($error->culprit ?? 'unknown') . ' _(confidence: ' . $error->confidence . ")_\n\n";

        if ($context !== null) {
            $body .= $this->contextBlock($context);
        }
        $body .= $this->environmentBlock() . $this->footer();

        return $this->buildUrl($issuesNewUrl, $title, $body);
    }

    /** @param list<array<string, mixed>> $trail */
    public function composeForContext(string $issuesNewUrl, BackendContext $context, ?CapturedError $error, array $trail): string
    {
        $title = 'Backend issue' . ($context->module !== null ? ' in ' . $context->module : '');
        $body = "### What happened\n\n_Please describe the problem._\n\n" . $this->contextBlock($context);

        if ($trail !== []) {
            $body .= $this->trailBlock($trail);
        }
        if ($error !== null) {
            $body .= "### Last recorded error\n\n```\n" . $error->exceptionClass . ': ' . $this->redact($error->message) . "\n```\n\n";
        }
        $body .= $this->environmentBlock() . $this->footer();

        return $this->buildUrl($issuesNewUrl, $title, $body);
    }

    /** @param list<array<string, mixed>> $trail */
    public function plainTextReport(BackendContext $context, ?CapturedError $error, array $trail): string
    {
        $lines = ['Backend issue report', ''];
        $lines[] = 'Module: ' . ($context->module ?? '-');
        $lines[] = 'URL: ' . ($context->url ?? '-');
        if ($context->params !== []) {
            $lines[] = 'Params: ' . $this->flattenParams($context->params);
        }
        if ($error !== null) {
            $lines[] = 'Last error: ' . $error->exceptionClass . ': ' . $this->redact($error->message);
        }
        foreach ($trail as $action) {
            $lines[] = 'Recent: ' . (string) ($action['method'] ?? '') . ' ' . (string) ($action['module'] ?? '');
        }
        $lines[] = 'Environment: TYPO3 ' . $this->typo3Version->getVersion() . ', PHP ' . PHP_VERSION;

        return implode("\n", $lines);
    }

    private function contextBlock(BackendContext $context): string
    {
        $block = "### Context\n\n";
        $block .= '- **Module:** ' . ($context->module ?? '-') . "\n";
        $block .= '- **Route:** ' . ($context->route ?? '-') . "\n";
        $block .= '- **URL:** ' . $this->redact((string) ($context->url ?? '-')) . "\n";
        if ($context->params !== []) {
            $block .= '- **Parameters:** ' . $this->flattenParams($context->params) . "\n";
        }

        return $block . "\n";
    }

    /** @param list<array<string, mixed>> $trail */
    private function trailBlock(array $trail): string
    {
        $block = "### Recent actions\n\n";
        foreach ($trail as $action) {
            $block .= '- `' . (string) ($action['method'] ?? '') . '` ' . (string) ($action['module'] ?? '') . "\n";
        }

        return $block . "\n";
    }

    private function environmentBlock(): string
    {
        return '**Environment:** TYPO3 ' . $this->typo3Version->getVersion() . ', PHP ' . PHP_VERSION . "\n\n";
    }

    private function footer(): string
    {
        return "---\n_Reported via nr_bug_reporter. Please review for sensitive data before submitting._";
    }

    /** @param array<string, string> $params */
    private function flattenParams(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $this->redact($value);
        }

        return implode(', ', $pairs);
    }

    private function buildUrl(string $issuesNewUrl, string $title, string $body): string
    {
        $compose = static fn (string $b): string => $issuesNewUrl . '?' . http_build_query([
            'title' => $title,
            'body' => $b,
            'labels' => 'bug',
        ]);

        $url = $compose($body);
        if (strlen($url) <= self::MAX_URL) {
            return $url;
        }

        // Trim the body so the whole URL fits under the practical length ceiling.
        $overflow = strlen($url) - self::MAX_URL;
        $note = "\n\n_(truncated — please paste full details manually)_";
        $keep = max(0, strlen($body) - $overflow - strlen($note) - 16);
        $body = substr($body, 0, $keep) . $note;

        return $compose($body);
    }

    private function redact(string $value): string
    {
        $value = (string) preg_replace(
            '~(?i)(password|passwd|token|secret|api[_-]?key|authorization|bearer)\s*[=:]\s*\S+~',
            '$1=[redacted]',
            $value,
        );

        $projectPath = Environment::getProjectPath();
        if ($projectPath !== '') {
            $value = str_replace($projectPath . '/', '', $value);
        }

        return $value;
    }
}
