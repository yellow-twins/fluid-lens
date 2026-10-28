<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * What {@see MessageBuilder} learned about a run of text and expressions.
 */
final class TextAnalysis
{
    public function __construct(
        public readonly string $display,
        public readonly string $plainText,
        public readonly int $letters,
        public readonly ?Message $message,
        public readonly ?string $reason,
    ) {
    }
}
