<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

use YellowTwins\FluidLens\Parser\Node;

/**
 * A parsed template: its file path paired with the root of its node tree.
 */
final class ParsedTemplate
{
    public function __construct(
        public readonly string $file,
        public readonly Node $tree,
    ) {
    }
}
