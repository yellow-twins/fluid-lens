<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

use RuntimeException;

/**
 * Thrown when a template cannot be read or parsed into a node tree.
 */
final class ParseException extends RuntimeException
{
}
