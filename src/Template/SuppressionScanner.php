<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

/**
 * Finds inline suppression markers in a template's source.
 *
 * A marker suppresses the element that follows it, so both the marker's own line
 * and the next line are treated as ignored. The marker works in any comment style
 * Fluid allows, since only the token is matched:
 *
 *   {# @fluidlint-ignore why this block stays inline #}
 *   <!-- @fluidlint-ignore -->
 */
final class SuppressionScanner
{
    public const MARKER = '@fluidlint-ignore';

    /**
     * @return array<int, true> the set of 1-based line numbers to ignore
     */
    public function scan(string $source): array
    {
        $ignored = [];
        $lines = explode("\n", $source);

        foreach ($lines as $index => $line) {
            if (str_contains($line, self::MARKER)) {
                $lineNumber = $index + 1;
                $ignored[$lineNumber] = true;
                $ignored[$lineNumber + 1] = true;
            }
        }

        return $ignored;
    }
}
