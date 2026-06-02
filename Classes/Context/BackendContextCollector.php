<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Context;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * Extracts the current backend module / route / URL and a small set of safe GET parameters from the
 * request. Module identifier resolution prefers the validated 'module' attribute and falls back to
 * the route's '_identifier' option for non-module routes.
 */
final class BackendContextCollector
{
    /** GET parameters that are useful for reproduction and safe to surface. */
    private const SAFE_PARAMS = ['id', 'edit', 'table', 'uid', 'action', 'controller'];

    public function fromRequest(ServerRequestInterface $request): BackendContext
    {
        $module = $request->getAttribute('module');
        $route = $request->getAttribute('route');

        $moduleId = $module instanceof ModuleInterface
            ? $module->getIdentifier()
            : ($route instanceof Route ? (string) ($route->getOption('_identifier') ?? '') : '');
        $routePath = $route instanceof Route ? (string) $route->getPath() : null;

        $normalizedParams = $request->getAttribute('normalizedParams');
        $url = $normalizedParams instanceof NormalizedParams ? $normalizedParams->getRequestUrl() : null;

        $params = [];
        $query = $request->getQueryParams();
        foreach (self::SAFE_PARAMS as $key) {
            $value = $query[$key] ?? null;
            if (is_scalar($value)) {
                $params[$key] = (string) $value;
            } elseif (is_array($value)) {
                // e.g. edit[pages][12]=edit — keep the keys, drop deep values
                $params[$key] = implode(',', array_keys($value));
            }
        }

        return new BackendContext($moduleId !== '' ? $moduleId : null, $routePath, $url, $params);
    }
}
