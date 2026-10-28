<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Config;

/**
 * The "i18n" section of the configuration. A null value falls back to the
 * command's default.
 */
final class I18nConfig
{
    /**
     * @param list<string> $ignore     texts to accept as they are ("/…/" for a regular expression)
     * @param list<string> $attributes further attribute names to treat as translatable
     */
    public function __construct(
        public readonly ?int $minLetters = null,
        public readonly array $ignore = [],
        public readonly array $attributes = [],
        public readonly ?string $file = null,
        public readonly ?string $style = null,
        public readonly ?string $language = null,
        public readonly ?string $sourceLanguage = null,
        public readonly ?string $baseline = null,
    ) {
    }
}
