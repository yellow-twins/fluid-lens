<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Small traversal helpers shared by the rules.
 */
final class Elements
{
    /**
     * Every element in the subtree, in document order.
     *
     * @return list<Node>
     */
    public static function all(Node $node): array
    {
        $elements = [];
        self::collect($node, $elements);

        return $elements;
    }

    /**
     * @param list<Node> $elements
     */
    private static function collect(Node $node, array &$elements): void
    {
        if ($node->isElement()) {
            $elements[] = $node;
        }

        foreach ($node->children() as $child) {
            self::collect($child, $elements);
        }
    }
}
