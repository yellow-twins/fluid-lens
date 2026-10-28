<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Decides which language file a template's texts belong in.
 *
 * - A template of a TYPO3 extension ({@code Resources/Private/…}) uses the
 *   extension's {@code Resources/Private/Language/locallang.xlf}, addressed with
 *   {@code extensionName}, as TYPO3 resolves it from anywhere.
 * - A Content Block template ({@code ContentBlocks/<type>/<name>/templates/…}) uses
 *   the block's own {@code language/labels.xlf}, addressed by its LLL:EXT: path.
 * - Anything else uses the configured fallback file, if one is set.
 *
 * The extension root is the nearest directory with a composer.json of type
 * typo3-cms-extension or an ext_emconf.php; the extension key comes from
 * {@code extra.typo3/cms.extension-key}, else from the directory name.
 */
final class TargetResolver
{
    private const CONTENT_BLOCK = '#^(?<block>.*/ContentBlocks/[^/]+/[^/]+)/templates/(?<template>.+)\.html$#';

    private const DEFAULT_FILE = 'Resources/Private/Language/locallang.xlf';

    /** @var array<string, array{root: string, key: string}|false> */
    private array $extensionCache = [];

    public function __construct(
        private readonly ?string $fallbackFile = null,
    ) {
    }

    public function resolve(string $template): ?Target
    {
        $template = $this->normalize($template);
        $extension = $this->extensionOf(dirname($template));

        if (preg_match(self::CONTENT_BLOCK, $template, $match) === 1) {
            return $this->contentBlockTarget($match['block'], $match['template'], $extension);
        }

        if ($extension !== null && str_starts_with($template, $extension['root'] . '/Resources/Private/')) {
            $relative = substr($template, strlen($extension['root'] . '/Resources/Private/'));

            return new Target(
                $extension['root'] . '/' . self::DEFAULT_FILE,
                $extension['key'],
                null,
                $this->prefixFor($relative),
                $extension['key'],
            );
        }

        return $this->fallbackTarget($template);
    }

    /**
     * @param array{root: string, key: string}|null $extension
     */
    private function contentBlockTarget(string $block, string $template, ?array $extension): ?Target
    {
        if ($extension === null) {
            return null;
        }

        $file = $block . '/language/labels.xlf';

        return new Target(
            $file,
            null,
            $this->lllPath($file, $extension),
            $this->prefixFor($template . '.html'),
            $extension['key'],
        );
    }

    private function fallbackTarget(string $template): ?Target
    {
        if ($this->fallbackFile === null) {
            return null;
        }

        $file = $this->normalize($this->fallbackFile);
        $extension = $this->extensionOf(dirname($file));
        if ($extension === null) {
            return null;
        }

        $segments = explode('/', $template);

        return new Target(
            $file,
            null,
            $this->lllPath($file, $extension),
            $this->prefixFor(implode('/', array_slice($segments, -2))),
            $extension['key'],
        );
    }

    /**
     * @param array{root: string, key: string} $extension
     */
    private function lllPath(string $file, array $extension): string
    {
        return sprintf('LLL:EXT:%s/%s', $extension['key'], substr($file, strlen($extension['root']) + 1));
    }

    /**
     * "Templates/Event/List.html" becomes "event.list"; the Templates, Partials
     * and Layouts folders carry no meaning for a label and are dropped.
     */
    private function prefixFor(string $relativePath): string
    {
        $path = (string) preg_replace('/\.html$/i', '', $relativePath);
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || in_array(strtolower($segment), ['templates', 'partials', 'layouts'], true)) {
                continue;
            }
            $parts[] = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $segment));
        }

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return $parts === [] ? 'template' : implode('.', $parts);
    }

    /**
     * @return array{root: string, key: string}|null
     */
    private function extensionOf(string $directory): ?array
    {
        $visited = [];
        while ($directory !== '' && $directory !== '.' && $directory !== '/') {
            if (isset($this->extensionCache[$directory])) {
                $found = $this->extensionCache[$directory];
                break;
            }
            $visited[] = $directory;

            $found = $this->extensionAt($directory);
            if ($found !== false) {
                break;
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }

        $found ??= false;
        foreach ($visited as $path) {
            $this->extensionCache[$path] = $found;
        }

        return $found === false ? null : $found;
    }

    /**
     * @return array{root: string, key: string}|false
     */
    private function extensionAt(string $directory): array|false
    {
        $composer = $this->composerManifest($directory . '/composer.json');
        if ($composer !== null && ($composer['type'] ?? null) === 'typo3-cms-extension') {
            $key = $composer['extra']['typo3/cms']['extension-key'] ?? null;

            return ['root' => $directory, 'key' => is_string($key) && $key !== '' ? $key : basename($directory)];
        }

        if (is_file($directory . '/ext_emconf.php')) {
            return ['root' => $directory, 'key' => basename($directory)];
        }

        return false;
    }

    /**
     * @return array{type?: mixed, extra?: array{'typo3/cms'?: array{extension-key?: mixed}}}|null
     */
    private function composerManifest(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        /** @var array{type?: mixed, extra?: array{'typo3/cms'?: array{extension-key?: mixed}}}|null */
        return is_array($data) ? $data : null;
    }

    private function normalize(string $path): string
    {
        $real = realpath($path);

        return rtrim(str_replace('\\', '/', $real !== false ? $real : $path), '/');
    }
}
