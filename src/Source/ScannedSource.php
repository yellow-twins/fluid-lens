<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Source;

/**
 * A template's source split into segments, which together cover every byte of
 * it in order.
 */
final class ScannedSource
{
    /** @var list<int> offset at which each line starts, 0-based line index */
    private readonly array $lineStarts;

    /**
     * @param list<Segment> $segments
     */
    public function __construct(
        public readonly string $source,
        public readonly array $segments,
    ) {
        $starts = [0];
        $offset = 0;
        while (($offset = strpos($source, "\n", $offset)) !== false) {
            $offset++;
            $starts[] = $offset;
        }
        $this->lineStarts = $starts;
    }

    /**
     * The 1-based line the given byte offset is on.
     */
    public function lineOf(int $offset): int
    {
        $low = 0;
        $high = count($this->lineStarts) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($this->lineStarts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low + 1;
    }
}
