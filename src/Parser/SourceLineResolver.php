<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

/**
 * Resolves the source line of each element as the tree is built.
 *
 * The tolerant HTML5 parser does not record source positions, so line numbers
 * are recovered from the original source instead. DOM nodes are produced in
 * document order, so a forward-only cursor can find each element's opening tag
 * by scanning for the next matching {@code <tagname} from where the previous
 * match ended.
 *
 * Elements the parser inserts implicitly (for example a {@code <tbody>} that the
 * source omits) are simply not found; the cursor then stays put so the following
 * real elements keep resolving correctly.
 */
final class SourceLineResolver
{
    private int $cursor = 0;

    public function __construct(
        private readonly string $source,
    ) {
    }

    /**
     * The 1-based line of the next occurrence of the given tag, or null when it
     * cannot be located (an implicitly inserted element).
     */
    public function resolve(string $tagName): ?int
    {
        // The look-ahead ensures the name ends here, so <a> never matches <article>.
        $pattern = '/<' . preg_quote($tagName, '/') . '(?=[\s\/>])/i';

        if (preg_match($pattern, $this->source, $matches, PREG_OFFSET_CAPTURE, $this->cursor) !== 1) {
            return null;
        }

        $offset = $matches[0][1];
        $this->cursor = $offset + 1;

        return substr_count($this->source, "\n", 0, $offset) + 1;
    }
}
