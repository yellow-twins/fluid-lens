<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * A piece of text written straight into a template instead of coming from a
 * language file.
 *
 * It is extractable when it has a {@see Message}; otherwise {@see $reason} says
 * why it has to be moved by hand.
 */
final class HardcodedText
{
    /**
     * @param string      $context where it stands, e.g. "p" or "img alt"
     * @param string      $text    the text as written, whitespace collapsed
     * @param int         $start   offset of the replaceable range in the source
     * @param int         $end     offset just past the replaceable range
     * @param string|null $quote   the quote around an attribute value
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly TextKind $kind,
        public readonly string $context,
        public readonly string $text,
        public readonly ?Message $message,
        public readonly ?string $reason,
        public readonly int $start,
        public readonly int $end,
        public readonly ?string $quote = null,
    ) {
    }

    public function isExtractable(): bool
    {
        return $this->message !== null;
    }
}
