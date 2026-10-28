<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Source;

/**
 * The kinds of piece a template's source is split into by {@see SourceScanner}.
 */
enum SegmentType: string
{
    /** Literal text between tags. */
    case Text = 'text';

    /** An opening, closing or self-closing tag. */
    case Tag = 'tag';

    /** A Fluid inline expression {@code {…}} inside text. */
    case Expression = 'expression';

    /** An HTML comment, a CDATA section or a declaration such as a doctype. */
    case Comment = 'comment';

    /** Content that is not markup: the body of script, style and f:comment. */
    case Raw = 'raw';
}
