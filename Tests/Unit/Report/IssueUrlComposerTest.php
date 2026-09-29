<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Tests\Unit\Report;

use Netresearch\NrBugReporter\Context\BackendContext;
use Netresearch\NrBugReporter\Report\IssueUrlComposer;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Redaction of the report text. Both outputs leave the backend: the prefilled issue URL goes to
 * GitHub when the user opens it, and the plain-text report is copied to the clipboard. Backend
 * request URLs carry the route `token` parameter, so the URL must be redacted in both.
 *
 * IssueUrlComposer reads Environment::getProjectPath() and Typo3Version; both are plain classes of
 * typo3/cms-core (a runtime dependency) and need no TYPO3 boot, only the static environment set.
 */
final class IssueUrlComposerTest extends TestCase
{
    private const PROJECT = '/var/www/project';
    private const URL = 'https://example.org/typo3/module/web/layout?token=0123456789abcdef&id=12';

    protected function setUp(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            self::PROJECT,
            self::PROJECT . '/public',
            self::PROJECT . '/var',
            self::PROJECT . '/config',
            self::PROJECT . '/vendor/bin/phpunit',
            'UNIX',
        );
    }

    private function context(): BackendContext
    {
        return new BackendContext('web_layout', '/module/web/layout', self::URL, ['id' => '12']);
    }

    public function testPrefilledIssueBodyRedactsTheRouteToken(): void
    {
        $url = (new IssueUrlComposer(new Typo3Version()))
            ->composeForContext('https://github.com/acme/repo/issues/new', $this->context(), null, []);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $body = (string) ($query['body'] ?? '');

        self::assertStringContainsString('token=[redacted]', $body, 'the URL line must still be present, redacted');
        self::assertStringNotContainsString('0123456789abcdef', $body, 'the route token must not reach the prefilled issue');
    }

    public function testPlainTextReportRedactsTheRouteToken(): void
    {
        $report = (new IssueUrlComposer(new Typo3Version()))->plainTextReport($this->context(), null, []);

        self::assertStringContainsString('URL: https://example.org/typo3/module/web/layout?token=[redacted]', $report, 'the URL line must still be present, redacted');
        self::assertStringNotContainsString('0123456789abcdef', $report, 'the route token must not reach the copy-to-clipboard text');
    }
}
