<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

/**
 * Shortens paths for display.
 */
final class Paths
{
    /**
     * The path relative to the working directory when it lies inside it.
     */
    public static function relative(string $path): string
    {
        if (str_starts_with($path, './')) {
            return substr($path, 2);
        }

        $cwd = getcwd();
        if ($cwd === false) {
            return $path;
        }

        $prefix = rtrim(str_replace('\\', '/', $cwd), '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
