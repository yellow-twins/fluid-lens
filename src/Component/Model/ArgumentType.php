<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * The Fluid type of a proposed component argument, as written in
 * {@code <f:argument type="…">}.
 */
enum ArgumentType: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Array = 'array';
    case Object = 'object';
    /** The value is passed through from the caller and its type is not visible statically. */
    case Mixed = 'mixed';
}
