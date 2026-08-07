<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Capture;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Persists the last captured error and a short action trail in the BE user session (ses_data),
 * so the proactive toolbar item can show "the last error" and "what you were doing" — even after
 * navigating away from the error page.
 */
final class SessionStore
{
    private const KEY_ERROR = 'tx_nrbugreporter_lastError';
    private const KEY_TRAIL = 'tx_nrbugreporter_actionTrail';
    private const TRAIL_MAX = 10;

    public function recordError(CapturedError $error): void
    {
        $this->backendUser()?->setAndSaveSessionData(self::KEY_ERROR, $error->toArray());
    }

    public function lastError(): ?CapturedError
    {
        $data = $this->backendUser()?->getSessionData(self::KEY_ERROR);

        return is_array($data) ? CapturedError::fromArray($data) : null;
    }

    public function clearError(): void
    {
        $this->backendUser()?->setAndSaveSessionData(self::KEY_ERROR, null);
    }

    /** @param array{module:?string, route:?string, method:string, time:int} $action */
    public function recordAction(array $action): void
    {
        $user = $this->backendUser();
        if ($user === null) {
            return;
        }
        $trail = $this->actionTrail();
        $trail[] = $action;
        if (count($trail) > self::TRAIL_MAX) {
            $trail = array_slice($trail, -self::TRAIL_MAX);
        }
        $user->setAndSaveSessionData(self::KEY_TRAIL, $trail);
    }

    /** @return list<array<string, mixed>> */
    public function actionTrail(): array
    {
        $data = $this->backendUser()?->getSessionData(self::KEY_TRAIL);

        return is_array($data) ? array_values($data) : [];
    }

    private function backendUser(): ?BackendUserAuthentication
    {
        $user = $GLOBALS['BE_USER'] ?? null;

        return $user instanceof BackendUserAuthentication ? $user : null;
    }
}
