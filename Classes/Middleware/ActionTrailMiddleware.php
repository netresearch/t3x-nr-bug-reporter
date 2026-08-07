<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Middleware;

use Netresearch\NrBugReporter\Capture\SessionStore;
use Netresearch\NrBugReporter\Context\BackendContextCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records a short trail of recent backend actions (module + method) in the BE session, so the
 * proactive toolbar report can include "what the user was doing". Throttled: only POST requests and
 * navigations to a *different* module are recorded, to avoid a session write on every page load.
 */
final class ActionTrailMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionStore $store,
        private readonly BackendContextCollector $collector,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $this->record($request);
        } catch (\Throwable) {
            // Telemetry must never break a backend request.
        }

        return $handler->handle($request);
    }

    private function record(ServerRequestInterface $request): void
    {
        $context = $this->collector->fromRequest($request);
        if ($context->module === null) {
            return;
        }

        $method = $request->getMethod();
        $trail = $this->store->actionTrail();
        $lastModule = $trail !== [] ? ($trail[array_key_last($trail)]['module'] ?? null) : null;

        // Skip repeated GET navigation to the same module; always record POSTs (real actions).
        if ($method !== 'POST' && $context->module === $lastModule) {
            return;
        }

        $this->store->recordAction([
            'module' => $context->module,
            'route' => $context->route,
            'method' => $method,
            'time' => time(),
        ]);
    }
}
