<?php

declare(strict_types=1);

/**
 * Maintained, curated "last resort" map: Composer package name => GitHub repository (or issues) URL.
 *
 * Consulted by GitHubTrackerResolver ONLY after (1) composer.json support.issues, (2) support.source/
 * homepage, and (3) the composer.lock / installed.json VCS source url all fail to yield a github.com
 * tracker. Each entry is an explicit human decision for a package we know is mirrored/tracked on
 * GitHub but whose machine-readable metadata does not say so.
 *
 * NEVER add private / first-party (client sitepackage) packages here: that would convert an internal
 * failure into a public, actionable bug report and leak project context (see red-team finding DEG-5).
 */

return [
    // Example only: a package distributed via a non-GitHub VCS whose canonical tracker we know.
    'acme/closed-source' => 'https://github.com/acme/closed-source',
];
