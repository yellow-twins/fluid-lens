<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

use YellowTwins\FluidLens\Parser\Node;

/**
 * A parsed template: its file path paired with the root of its node tree.
 *
 * The raw source is kept alongside because the tree is normalised by the HTML5
 * parser (lowercased names, no {@code xmlns:*} attributes); analyses that need
 * the original spelling or namespace declarations read it from here.
 */
final class ParsedTemplate
{
    public function __construct(
        public readonly string $file,
        public readonly Node $tree,
        public readonly string $source = '',
    ) {
    }
}
