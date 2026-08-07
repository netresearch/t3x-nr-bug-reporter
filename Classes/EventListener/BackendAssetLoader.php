<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Loads the toolbar JavaScript module on every backend page (it wires up the "copy report" button in
 * the toolbar dropdown). Registered automatically via the #[AsEventListener] attribute.
 */
final readonly class BackendAssetLoader
{
    public function __construct(private PageRenderer $pageRenderer) {}

    #[AsEventListener(identifier: 'nr-bug-reporter/backend-assets')]
    public function __invoke(AfterBackendPageRenderEvent $event): void
    {
        $this->pageRenderer->loadJavaScriptModule('@netresearch/nr-bug-reporter/report-toolbar.js');
    }
}
