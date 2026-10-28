<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

/**
 * Renders the difference between two texts as a unified diff, so a planned
 * change can be reviewed (or piped into {@code git apply}) before anything is
 * written.
 */
final class UnifiedDiff
{
    private const CONTEXT = 2;

    /**
     * Above this many line comparisons the texts are shown as one replaced hunk
     * instead of being aligned line by line.
     */
    private const MAX_CELLS = 1_000_000;

    public function render(string $path, ?string $before, string $after): string
    {
        $old = $before === null || $before === '' ? [] : $this->lines($before);
        $new = $this->lines($after);

        $header = sprintf("--- %s\n+++ %s\n", $before === null ? '/dev/null' : 'a/' . $path, 'b/' . $path);

        return $header . $this->hunks($old, $new);
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $lines = explode("\n", $text);
        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     */
    private function hunks(array $old, array $new): string
    {
        $operations = $this->operations($old, $new);
        $output = '';
        $count = count($operations);
        $index = 0;

        while ($index < $count) {
            if ($operations[$index][0] === ' ') {
                $index++;
                continue;
            }

            $start = max(0, $index - self::CONTEXT);
            $end = $index;
            // Extend the hunk while further changes follow within twice the context.
            for ($cursor = $index; $cursor < $count; $cursor++) {
                if ($operations[$cursor][0] !== ' ') {
                    $end = $cursor;
                } elseif ($cursor - $end > 2 * self::CONTEXT) {
                    break;
                }
            }
            $end = min($count - 1, $end + self::CONTEXT);

            $output .= $this->hunk(array_slice($operations, $start, $end - $start + 1));
            $index = $end + 1;
        }

        return $output;
    }

    /**
     * @param list<array{string, string, int, int}> $operations
     */
    private function hunk(array $operations): string
    {
        $oldStart = $operations[0][2];
        $newStart = $operations[0][3];
        $oldCount = 0;
        $newCount = 0;
        $body = '';

        foreach ($operations as [$marker, $line]) {
            $oldCount += $marker === '+' ? 0 : 1;
            $newCount += $marker === '-' ? 0 : 1;
            $body .= $marker . $line . "\n";
        }

        return sprintf(
            "@@ -%d,%d +%d,%d @@\n",
            $oldCount === 0 ? $oldStart : $oldStart + 1,
            $oldCount,
            $newCount === 0 ? $newStart : $newStart + 1,
            $newCount,
        ) . $body;
    }

    /**
     * The edit script: each entry is the marker (" ", "-", "+"), the line, and
     * the 0-based positions in the old and new text where it applies.
     *
     * @param list<string> $old
     * @param list<string> $new
     *
     * @return list<array{string, string, int, int}>
     */
    private function operations(array $old, array $new): array
    {
        $oldCount = count($old);
        $newCount = count($new);

        if ($oldCount * $newCount > self::MAX_CELLS) {
            $removed = array_map(
                static fn (string $line, int $i): array => ['-', $line, $i, 0],
                $old,
                array_keys($old),
            );
            $added = array_map(
                static fn (string $line, int $i): array => ['+', $line, $oldCount, $i],
                $new,
                array_keys($new),
            );

            return array_merge($removed, $added);
        }

        $lengths = $this->commonLengths($old, $new);
        $operations = [];
        $i = 0;
        $j = 0;
        while ($i < $oldCount || $j < $newCount) {
            if ($i < $oldCount && $j < $newCount && $old[$i] === $new[$j]) {
                $operations[] = [' ', $old[$i], $i++, $j++];
            } elseif ($i < $oldCount && ($j === $newCount || $lengths[$i + 1][$j] >= $lengths[$i][$j + 1])) {
                $operations[] = ['-', $old[$i], $i++, $j];
            } else {
                $operations[] = ['+', $new[$j], $i, $j++];
            }
        }

        return $operations;
    }

    /**
     * Longest-common-subsequence lengths of every pair of suffixes.
     *
     * @param list<string> $old
     * @param list<string> $new
     *
     * @return array<int, array<int, int>>
     */
    private function commonLengths(array $old, array $new): array
    {
        $oldCount = count($old);
        $newCount = count($new);
        $lengths = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($i = $oldCount - 1; $i >= 0; $i--) {
            for ($j = $newCount - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $old[$i] === $new[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        return $lengths;
    }
}
