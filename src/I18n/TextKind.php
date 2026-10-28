<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Where a hardcoded text was found.
 */
enum TextKind: string
{
    /** Text content between tags. */
    case Text = 'text';

    /** The value of a translatable attribute or ViewHelper argument. */
    case Attribute = 'attribute';
}
