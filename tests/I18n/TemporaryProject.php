<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\I18n;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A throw-away directory tree with TYPO3 extensions, for tests that read and
 * write real files.
 */
final class TemporaryProject
{
    public readonly string $root;

    public function __construct()
    {
        $root = sys_get_temp_dir() . '/fluid-lens-i18n-' . uniqid('', true);
        mkdir($root, 0777, true);
        $this->root = (string) realpath($root);
    }

    public function addExtension(string $directory, string $key): string
    {
        $manifest = ['type' => 'typo3-cms-extension', 'extra' => ['typo3/cms' => ['extension-key' => $key]]];

        return $this->write($directory . '/composer.json', (string) json_encode($manifest));
    }

    public function write(string $path, string $content): string
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);

        return $file;
    }

    public function read(string $path): string
    {
        return (string) file_get_contents($this->root . '/' . $path);
    }

    public function path(string $path): string
    {
        return $this->root . '/' . $path;
    }

    public function remove(): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
    }
}
