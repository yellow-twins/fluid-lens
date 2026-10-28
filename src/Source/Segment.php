<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Source;

/**
 * A contiguous piece of a template's source, covering the bytes
 * {@code [start, end)} of the original text.
 */
final class Segment
{
    public function __construct(
        public readonly SegmentType $type,
        public readonly int $start,
        public readonly int $end,
        public readonly string $text,
        public readonly ?TagToken $tag = null,
    ) {
    }

    public function isTag(): bool
    {
        return $this->tag !== null;
    }
}
