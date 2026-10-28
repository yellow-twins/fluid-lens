<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * A label to add to a language file.
 */
final class TranslationUnit
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly ?string $target = null,
    ) {
    }
}
