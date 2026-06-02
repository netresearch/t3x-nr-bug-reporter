<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Attribution;

/**
 * Maps an absolute file path (from a stack-trace frame) to the Composer package that owns it,
 * and exposes each package's composer.json manifest.
 *
 * The production source of truth is Composer\InstalledVersions::getAllRawData(); this class is
 * deliberately framework-agnostic so it can be unit-tested against a fixed dataset and reused
 * outside a booted TYPO3.
 */
final class PackageIndex
{
    /** @var list<array{name:string, path:?string, type:string}> */
    private array $packages = [];

    /** @var array<string, ?string> package name => realpath of install dir */
    private array $pathByName = [];

    /** @var array<string, string> package name => VCS source url (from composer.lock / installed.json) */
    private array $sourceByName = [];

    /** @var array<string, string> PSR-4 namespace prefix => package name */
    private array $namespaceMap = [];

    /** @var array<string, string> package name => composer type */
    private array $typeByName = [];

    /**
     * Build from the structure returned by Composer\InstalledVersions::getAllRawData().
     *
     * @param list<array{root?:array<string,mixed>, versions?:array<string,array<string,mixed>>}> $datasets
     */
    public static function fromGetAllRawData(array $datasets): self
    {
        $index = new self();
        foreach ($datasets as $dataset) {
            foreach (($dataset['versions'] ?? []) as $name => $info) {
                $index->add((string) $name, $info['install_path'] ?? null, (string) ($info['type'] ?? 'library'));
            }
            if (isset($dataset['root']['name'])) {
                $index->add(
                    (string) $dataset['root']['name'],
                    $dataset['root']['install_path'] ?? null,
                    (string) ($dataset['root']['type'] ?? 'application'),
                );
            }
        }

        return $index;
    }

    public function add(string $name, ?string $path, string $type, ?string $sourceUrl = null): void
    {
        $normalised = null;
        if ($path !== null && $path !== '') {
            // install_path entries frequently contain "/../" (e.g. vendor/composer/../doctrine/dbal),
            // so realpath() is required. Fall back to the raw path for not-yet-existing dirs (tests).
            $normalised = realpath($path) ?: rtrim($path, '/');
        }

        $this->packages[] = ['name' => $name, 'path' => $normalised, 'type' => $type];
        $this->pathByName[$name] = $normalised;
        $this->typeByName[$name] = $type;
        if ($sourceUrl !== null && $sourceUrl !== '') {
            $this->sourceByName[$name] = $sourceUrl;
        }
    }

    /** Register a PSR-4 namespace prefix => package mapping (for class-FQCN attribution). */
    public function addPsr4(string $prefix, string $package): void
    {
        $this->namespaceMap[$prefix] = $package;
    }

    /**
     * Populate PSR-4 namespace => package mappings from a composer.lock or installed.json
     * (each package entry carries its own autoload.psr-4 prefixes).
     */
    public function loadNamespaces(string $lockOrInstalledJsonPath): void
    {
        if (!is_file($lockOrInstalledJsonPath)) {
            return;
        }
        $json = json_decode((string) file_get_contents($lockOrInstalledJsonPath), true);
        if (!is_array($json)) {
            return;
        }

        $lists = [];
        if (isset($json['packages']) && is_array($json['packages'])) {
            $lists[] = $json['packages'];
        }
        if ($lists === [] && array_is_list($json)) {
            $lists[] = $json;
        }

        foreach ($lists as $list) {
            foreach ($list as $package) {
                $name = $package['name'] ?? null;
                $psr4 = $package['autoload']['psr-4'] ?? null;
                if (!is_string($name) || !is_array($psr4)) {
                    continue;
                }
                foreach (array_keys($psr4) as $prefix) {
                    if (is_string($prefix) && $prefix !== '' && !isset($this->namespaceMap[$prefix])) {
                        $this->namespaceMap[$prefix] = $name;
                    }
                }
            }
        }
    }

    /**
     * Resolve a class FQCN to its owning package via the longest matching PSR-4 namespace prefix.
     * This reflects "whose code is running" — for a trait method, PHP reports the *using* class, so
     * the consuming package is resolved rather than the trait's defining package.
     *
     * @return array{name:string, type:string}|null
     */
    public function resolveClass(string $fqcn): ?array
    {
        $fqcn = ltrim($fqcn, '\\');
        $best = null;
        $bestLen = -1;
        foreach ($this->namespaceMap as $prefix => $package) {
            if (str_starts_with($fqcn, $prefix) && strlen($prefix) > $bestLen) {
                $best = $package;
                $bestLen = strlen($prefix);
            }
        }
        if ($best === null) {
            return null;
        }

        return ['name' => $best, 'type' => $this->typeByName[$best] ?? 'library'];
    }

    /**
     * Populate per-package VCS source URLs from a composer.lock or vendor/composer/installed.json.
     * Both formats carry `source.url` (and `dist.url`) per package even when the package's own
     * composer.json `support` block is empty — a strong fallback for tracker resolution.
     */
    public function loadSourceUrls(string $lockOrInstalledJsonPath): void
    {
        if (!is_file($lockOrInstalledJsonPath)) {
            return;
        }
        $json = json_decode((string) file_get_contents($lockOrInstalledJsonPath), true);
        if (!is_array($json)) {
            return;
        }

        $lists = [];
        if (isset($json['packages']) && is_array($json['packages'])) {
            $lists[] = $json['packages'];           // composer.lock + installed.json (composer 2)
        }
        if (isset($json['packages-dev']) && is_array($json['packages-dev'])) {
            $lists[] = $json['packages-dev'];        // composer.lock
        }
        if ($lists === [] && array_is_list($json)) {
            $lists[] = $json;                        // installed.json (composer 1 bare list)
        }

        foreach ($lists as $list) {
            foreach ($list as $package) {
                $name = $package['name'] ?? null;
                if (!is_string($name)) {
                    continue;
                }
                $url = $package['source']['url'] ?? $package['dist']['url'] ?? null;
                // Do not overwrite an explicitly-added source (e.g. from add()).
                if (is_string($url) && $url !== '' && !isset($this->sourceByName[$name])) {
                    $this->sourceByName[$name] = $url;
                }
            }
        }
    }

    public function sourceUrl(string $name): ?string
    {
        return $this->sourceByName[$name] ?? null;
    }

    /**
     * Resolve a file to its owning package using the longest matching path prefix.
     * Returns null when no installed package owns the file (closures, eval'd / compiled code, ...).
     *
     * @return array{name:string, path:string, type:string}|null
     */
    public function resolve(string $file): ?array
    {
        $real = realpath($file) ?: $file;

        $best = null;
        $bestLen = -1;
        foreach ($this->packages as $package) {
            $path = $package['path'];
            if ($path === null) {
                continue;
            }
            $prefix = rtrim($path, '/') . '/';
            if (str_starts_with($real, $prefix) && strlen($path) > $bestLen) {
                $best = $package;
                $bestLen = strlen($path);
            }
        }

        /** @var array{name:string, path:string, type:string}|null $best */
        return $best;
    }

    /**
     * @return array<string, mixed>|null decoded composer.json of the package, or null
     */
    public function composerManifest(string $packageName): ?array
    {
        $path = $this->pathByName[$packageName] ?? null;
        if ($path === null) {
            return null;
        }

        $file = $path . '/composer.json';
        if (!is_file($file)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return list<array{name:string, path:?string, type:string}> */
    public function all(): array
    {
        return $this->packages;
    }
}
