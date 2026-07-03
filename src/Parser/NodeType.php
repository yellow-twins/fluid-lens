<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

/**
 * The kind of node in a parsed Fluid template tree.
 *
 * Fluid ViewHelper tags (for example {@code <f:image>}) are represented as
 * {@see NodeType::Element} nodes just like plain HTML elements; the tag name
 * carries the namespace prefix. Inline expressions such as {@code {product.title}}
 * are kept verbatim inside {@see NodeType::Text} nodes and treated as opaque.
 */
enum NodeType: string
{
    case Root = 'root';
    case Element = 'element';
    case Text = 'text';
    case Comment = 'comment';
}
