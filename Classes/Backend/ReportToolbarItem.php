<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Backend;

use Netresearch\NrBugReporter\Capture\SessionStore;
use Netresearch\NrBugReporter\Context\BackendContextCollector;
use Netresearch\NrBugReporter\Report\IssueUrlComposer;
use Netresearch\NrBugReporter\Resolver\GitHubTrackerResolver;
use Netresearch\NrBugReporter\Service\PackageIndexProvider;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;

/**
 * Proactive "Report an issue" item in the backend top toolbar. Its dropdown surfaces the current
 * backend context, the last captured error (with a one-click GitHub link when one was resolved), and
 * recent actions — and offers a prefilled report either to an admin-configured repository or, when
 * none is configured, as copy-to-clipboard text.
 *
 * Registered automatically via DI: EXT:backend auto-tags every ToolbarItemInterface service.
 */
#[Autoconfigure(public: true)]
final class ReportToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly BackendContextCollector $collector,
        private readonly SessionStore $store,
        private readonly IssueUrlComposer $composer,
        private readonly PackageIndexProvider $indexProvider,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function checkAccess(): bool
    {
        return ($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication;
    }

    public function getItem(): string
    {
        return $this->iconFactory->getIcon('nrbugreporter-toolbar', IconSize::SMALL)->render();
    }

    public function hasDropDown(): bool
    {
        return true;
    }

    public function getAdditionalAttributes(): array
    {
        return ['class' => 'nr-bug-reporter-toolbar-item'];
    }

    public function getIndex(): int
    {
        return 25;
    }

    public function getDropDown(): string
    {
        if (!$this->checkAccess() || $this->request === null) {
            return '';
        }

        $context = $this->collector->fromRequest($this->request);
        $lastError = $this->store->lastError();
        $trail = $this->store->actionTrail();

        $html = '<h3 class="dropdown-headline">Report an issue</h3>';
        $html .= '<div class="dropdown-table"><div class="dropdown-table-row"><div class="dropdown-table-column">';
        $html .= '<p><strong>Module:</strong> ' . $this->esc($context->module ?? '-') . '<br>';
        $html .= '<strong>URL:</strong> ' . $this->esc((string) ($context->url ?? '-')) . '</p>';
        $html .= '</div></div></div>';

        $html .= $this->lastErrorBlock($lastError);
        $html .= $this->trailBlock($trail);
        $html .= $this->reportActionBlock($context, $lastError, $trail);

        return $html;
    }

    private function lastErrorBlock(?\Netresearch\NrBugReporter\Capture\CapturedError $error): string
    {
        if ($error === null) {
            return '';
        }

        $summary = $this->esc($error->exceptionClass . ': ' . $error->message);
        if ($error->offerReport && $error->trackerUrl !== null) {
            return '<hr><p><strong>Last error</strong><br>' . $summary . '<br>'
                . '<a class="btn btn-default btn-sm" href="' . $this->esc($error->trackerUrl) . '" target="_blank" rel="noopener noreferrer">'
                . 'Report this error to ' . $this->esc((string) $error->culprit) . '</a></p>';
        }

        return '<hr><p><strong>Last error</strong><br>' . $summary
            . '<br><em>No one-click report (' . $this->esc((string) ($error->culprit ?? 'unknown'))
            . ', ' . $this->esc($error->confidence) . ', ' . $this->esc($error->trackerStatus) . ')</em></p>';
    }

    /** @param list<array<string, mixed>> $trail */
    private function trailBlock(array $trail): string
    {
        if ($trail === []) {
            return '';
        }
        $items = '';
        foreach (array_reverse($trail) as $action) {
            $items .= '<li><code>' . $this->esc((string) ($action['method'] ?? '')) . '</code> '
                . $this->esc((string) ($action['module'] ?? '')) . '</li>';
        }

        return '<hr><p><strong>Recent actions</strong></p><ul class="list-unstyled">' . $items . '</ul>';
    }

    /** @param list<array<string, mixed>> $trail */
    private function reportActionBlock(
        \Netresearch\NrBugReporter\Context\BackendContext $context,
        ?\Netresearch\NrBugReporter\Capture\CapturedError $error,
        array $trail,
    ): string {
        $issuesNewUrl = $this->defaultIssuesNewUrl();
        if ($issuesNewUrl !== null) {
            $url = $this->composer->composeForContext($issuesNewUrl, $context, $error, $trail);

            return '<hr><p><a class="btn btn-primary btn-sm" href="' . $this->esc($url)
                . '" target="_blank" rel="noopener noreferrer">Report a problem with this page</a></p>';
        }

        // No configured target repository: offer the context as copyable text.
        $report = $this->composer->plainTextReport($context, $error, $trail);

        return '<hr><p><strong>Copy report</strong> <em>(configure a target repository in the extension settings for one-click reporting)</em></p>'
            . '<textarea class="form-control" rows="6" readonly data-nr-bug-reporter-report>' . $this->esc($report) . '</textarea>'
            . '<button type="button" class="btn btn-default btn-sm" data-nr-bug-reporter-copy style="margin-top:6px;">Copy to clipboard</button>';
    }

    private function defaultIssuesNewUrl(): ?string
    {
        try {
            $repository = trim((string) ($this->extensionConfiguration->get('nr_bug_reporter', 'defaultReportRepository') ?? ''));
        } catch (\Throwable) {
            return null;
        }
        if ($repository === '') {
            return null;
        }

        return (new GitHubTrackerResolver($this->indexProvider->get()))->issuesNewUrl($repository);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
