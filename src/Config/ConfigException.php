<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Config;

use RuntimeException;

/**
 * Thrown when a configuration file is requested but missing or malformed.
 */
final class ConfigException extends RuntimeException
{
}
