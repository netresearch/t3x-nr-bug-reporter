<?php

return [
    'backend' => [
        // Record a short trail of recent backend actions into the BE user session so the proactive
        // "Report issue" toolbar item can include "what I was doing" context. Anchored after the
        // module validator so the 'module' request attribute and $GLOBALS['BE_USER'] are populated.
        'netresearch/nr-bug-reporter/action-trail' => [
            'target' => \Netresearch\NrBugReporter\Middleware\ActionTrailMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
        ],
    ],
];
