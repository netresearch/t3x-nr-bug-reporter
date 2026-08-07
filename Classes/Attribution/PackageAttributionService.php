<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Attribution;

/**
 * Picks the most-likely culprit package from an ordered list of stack-trace file paths.
 *
 * Input order is INNERMOST -> OUTERMOST. The innermost element must be the throw site
 * (Throwable::getFile()); subsequent elements are the `file` of each Throwable::getTrace()
 * frame — which is the *caller's* location, not the callee's definition. The caller of
 * attribute() is responsible for assembling [getFile(), frame0.file, frame1.file, ...].
 *
 * Heuristic:
 *   1. Walk innermost -> outermost, classify each frame's owning package.
 *   2. First extension / non-infra-library frame wins (extension => high, library => medium).
 *   3. Otherwise the first infrastructure-library frame wins (low confidence).
 *   4. Otherwise, if only TYPO3 core frames were seen, return CORE (forge / suppress).
 *   5. Otherwise return null (compiled template / closure / empty trace — nothing to blame).
 *
 * The selection is deliberately probabilistic; the full ranked list is returned for human override.
 */
final class PackageAttributionService
{
    /** Vendor packages that are framework plumbing — skipped unless they are the only candidate. */
    private const INFRA_PREFIXES = [
        'symfony/', 'doctrine/', 'psr/', 'guzzlehttp/', 'monolog/', 'composer/',
        'nikic/', 'laminas/', 'league/', 'ramsey/', 'egulias/', 'masterminds/',
        'phpdocumentor/', 'webmozart/',
    ];

    public function __construct(
        private readonly PackageIndex $index,
        private readonly string $selfPackage = 'netresearch/nr-bug-reporter',
    ) {}

    /**
     * @param list<string|array{file?:?string, class?:?string}|null> $frames innermost->outermost;
     *        $frames[0] = throw site. A frame may be a bare file path (back-compat) or {file, class}.
     * @param list<string|array{file?:?string, class?:?string}>|null $rootCauseFrames when the exception
     *        was re-wrapped, the deepest $previous (root-cause) frames — attribution runs on these.
     */
    public function attribute(array $frames, ?array $rootCauseFrames = null): AttributionResult
    {
        // $previous-walk: a re-wrapped exception's own trace points at the wrapper; the real origin is
        // the deepest $previous. When supplied, attribute on the root cause, not the wrapper.
        if ($rootCauseFrames !== null && $rootCauseFrames !== []) {
            $frames = $rootCauseFrames;
        }

        // First pass: resolve every frame to its owning package + classification + dispatcher flag.
        $resolved = [];
        foreach ($frames as $frame) {
            [$file, $fqcn] = $this->normaliseFrame($frame);
            if ($file === null && $fqcn === null) {
                $resolved[] = ['class' => 'unknown', 'isDispatcher' => false];
                continue;
            }

            // Class-FQCN resolution first ("whose code is running"): for a trait method PHP reports the
            // *using* class, so this attributes to the consuming package, not the trait's defining one.
            $package = $fqcn !== null ? $this->index->resolveClass($fqcn) : null;
            if ($package === null && $file !== null) {
                $package = $this->index->resolve($file);
            }

            $resolved[] = [
                'package' => $package['name'] ?? null,
                'type' => $package['type'] ?? null,
                'file' => $file ?? ($fqcn ?? ''),
                'class' => $this->classify($file ?? '', $package),
                'isDispatcher' => $this->isDispatcher($file, $fqcn),
            ];
        }

        // Second pass: collect candidates, flagging those reached *through* a PSR-14/PSR-15 dispatcher
        // (the frame immediately outward is a dispatcher) — such a frame is a passive listener/middleware
        // that likely received bad input from an emitter further upstream.
        $candidates = [];
        $infra = [];
        $coreSeen = false;
        $unknownSeen = false;
        foreach ($resolved as $i => $row) {
            switch ($row['class']) {
                case 'self':
                    break;
                case 'core':
                    $coreSeen = true;
                    break;
                case 'infra':
                    $infra[] = $row;
                    break;
                case 'extension':
                case 'library':
                    $row['dispatched'] = ($resolved[$i + 1]['isDispatcher'] ?? false);
                    $candidates[] = $row;
                    break;
                default:
                    $unknownSeen = true;
            }
        }

        if ($candidates !== []) {
            // Prefer the first candidate NOT reached through a dispatcher.
            foreach ($candidates as $candidate) {
                if (!($candidate['dispatched'] ?? false)) {
                    return new AttributionResult(
                        $candidate['package'],
                        $candidate['class'] === 'extension' ? 'high' : 'medium',
                        $candidates,
                        sprintf('innermost %s frame (%s)', $candidate['class'], (string) $candidate['package']),
                    );
                }
            }

            // All candidates are dispatched (event listeners / middleware): the real culprit is likely
            // the emitter upstream. Report low confidence so the gate withholds a one-click report.
            $winner = $candidates[0];

            return new AttributionResult(
                $winner['package'],
                'low',
                $candidates,
                sprintf('%s frame reached via a dispatcher (passive consumer); emitter likely upstream', $winner['class']),
            );
        }

        if ($infra !== []) {
            return new AttributionResult(
                $infra[0]['package'],
                'low',
                $infra,
                'only infrastructure-library frames; most likely a misuse from core / site code',
            );
        }

        if ($coreSeen) {
            return new AttributionResult(
                AttributionResult::CORE,
                'core',
                [],
                'only TYPO3 core frames -> forge.typo3.org (outside the GitHub MVP gate)',
            );
        }

        return new AttributionResult(
            null,
            'none',
            [],
            $unknownSeen
                ? 'no owning package (compiled template / closure / eval)'
                : 'empty trace',
        );
    }

    /** @param array{name:string, path:string, type:string}|null $package */
    private function classify(string $file, ?array $package): string
    {
        // Compiled Fluid / cached code has no source owner even though it sits under a package path.
        if (preg_match('#(/var/cache/|/typo3temp/)#', $file) === 1) {
            return 'unknown';
        }

        $name = $package['name'] ?? null;
        $type = $package['type'] ?? null;

        // Core, both layouts: monorepo (path under typo3/sysext, or the root typo3/cms package)
        // and project installs (real typo3/cms-* packages of type typo3-cms-framework).
        if (str_contains($file, '/typo3/sysext/')) {
            return 'core';
        }
        if ($name === 'typo3/cms' || ($name !== null && str_starts_with($name, 'typo3/cms-'))) {
            return 'core';
        }
        if ($type === 'typo3-cms-framework' || $type === 'typo3-cms-core') {
            return 'core';
        }

        if ($package === null) {
            return 'unknown';
        }
        if ($name === $this->selfPackage) {
            return 'self';
        }
        if ($type === 'typo3-cms-extension') {
            return 'extension';
        }
        if ($name !== null && $this->isInfrastructure($name)) {
            return 'infra';
        }

        return 'library';
    }

    private function isInfrastructure(string $name): bool
    {
        foreach (self::INFRA_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** A PSR-14 event dispatcher or PSR-15 middleware dispatcher frame (by class FQCN or file). */
    private function isDispatcher(?string $file, ?string $fqcn): bool
    {
        $haystack = ($fqcn ?? '') . '|' . ($file ?? '');

        return preg_match('~(EventDispatcher|MiddlewareDispatcher|ListenerProvider)~', $haystack) === 1;
    }

    /**
     * @param string|array{file?:?string, class?:?string}|null $frame
     * @return array{0:?string, 1:?string} [file, classFqcn]
     */
    private function normaliseFrame(string|array|null $frame): array
    {
        if (is_string($frame)) {
            return [$frame === '' ? null : $frame, null];
        }
        if ($frame === null) {
            return [null, null];
        }
        $file = isset($frame['file']) && is_string($frame['file']) && $frame['file'] !== '' ? $frame['file'] : null;
        $class = isset($frame['class']) && is_string($frame['class']) && $frame['class'] !== '' ? $frame['class'] : null;

        return [$file, $class];
    }
}
