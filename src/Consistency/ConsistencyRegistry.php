<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Consistency\Check\IconSetCheck;
use YellowTwins\FluidLens\Consistency\Check\SliderLibraryCheck;
use YellowTwins\FluidLens\Support\Wildcard;

/**
 * The built-in consistency checks, and selection of them by name (with a
 * trailing `*` wildcard) for the `--only` / `--exclude` options.
 */
final class ConsistencyRegistry
{
    /**
     * @return list<ConsistencyCheck>
     */
    public static function default(): array
    {
        return [
            new SliderLibraryCheck(),
            new IconSetCheck(),
        ];
    }

    /**
     * @param list<ConsistencyCheck> $checks
     * @param list<string>           $only
     * @param list<string>           $exclude
     *
     * @return list<ConsistencyCheck>
     */
    public static function select(array $checks, array $only, array $exclude): array
    {
        return array_values(array_filter(
            $checks,
            static function (ConsistencyCheck $check) use ($only, $exclude): bool {
                if ($only !== [] && !Wildcard::matchesAny($check->name(), $only)) {
                    return false;
                }

                return !Wildcard::matchesAny($check->name(), $exclude);
            },
        ));
    }
}
