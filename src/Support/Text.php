<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Extracts the visible text of a subtree (including opaque `{expressions}` as
 * their literal text), collapsed to single spaces.
 */
final class Text
{
    public static function content(Node $node): string
    {
        return trim((string) preg_replace('/\s+/', ' ', self::gather($node)));
    }

    private static function gather(Node $node): string
    {
        $text = '';
        foreach ($node->children() as $child) {
            if ($child->type === NodeType::Text) {
                $text .= ' ' . $child->text;
            } elseif ($child->isElement()) {
                $text .= ' ' . self::gather($child);
            }
        }

        return $text;
    }
}
