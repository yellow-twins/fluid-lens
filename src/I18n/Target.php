<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * The language file a template's texts go into, and how the template refers to
 * a label in it.
 */
final class Target
{
    /**
     * @param string      $file          path of the language file (the default language)
     * @param string|null $extensionName set when f:translate can address the file through extensionName
     *                                   (it is the extension's locallang.xlf)
     * @param string|null $lllPath       the LLL:EXT:… path otherwise
     * @param string      $keyPrefix     prefix for generated keys, derived from the template path
     */
    public function __construct(
        public readonly string $file,
        public readonly ?string $extensionName,
        public readonly ?string $lllPath,
        public readonly string $keyPrefix,
        public readonly ?string $extensionKey = null,
    ) {
    }

    /**
     * The f:translate arguments that address the given key.
     *
     * @return array<string, string> argument name => value
     */
    public function translateArguments(string $key): array
    {
        if ($this->extensionName !== null) {
            return ['key' => $key, 'extensionName' => $this->extensionName];
        }

        return ['key' => sprintf('%s:%s', (string) $this->lllPath, $key)];
    }

    /**
     * The file holding the translation into the given language, e.g.
     * de.locallang.xlf beside locallang.xlf.
     */
    public function languageFile(string $language): string
    {
        return dirname($this->file) . '/' . $language . '.' . basename($this->file);
    }

    /**
     * The value of the file's {@code original} attribute when it is created.
     */
    public function original(): string
    {
        return $this->lllPath !== null ? substr($this->lllPath, strlen('LLL:')) : basename($this->file);
    }
}
