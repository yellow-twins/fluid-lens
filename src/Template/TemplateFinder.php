<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Resolves a path into the list of Fluid template files to analyse.
 *
 * A single file is returned as-is; a directory is scanned recursively for
 * `.html` files, skipping dependency folders that are never authored by hand.
 */
final class TemplateFinder
{
    /**
     * @var list<string>
     */
    private const SKIPPED_SEGMENTS = ['/vendor/', '/node_modules/', '/.git/'];

    /**
     * @return list<string>
     */
    public function find(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo && $this->isTemplate($entry)) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function isTemplate(SplFileInfo $entry): bool
    {
        if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'html') {
            return false;
        }

        foreach (self::SKIPPED_SEGMENTS as $segment) {
            if (str_contains($entry->getPathname(), $segment)) {
                return false;
            }
        }

        return true;
    }
}
