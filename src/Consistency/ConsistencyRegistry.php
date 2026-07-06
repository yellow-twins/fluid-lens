<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Consistency\Check\AnimationCheck;
use YellowTwins\FluidLens\Consistency\Check\CookieConsentCheck;
use YellowTwins\FluidLens\Consistency\Check\CssFrameworkCheck;
use YellowTwins\FluidLens\Consistency\Check\GridLayoutCheck;
use YellowTwins\FluidLens\Consistency\Check\IconSetCheck;
use YellowTwins\FluidLens\Consistency\Check\ImageApproachCheck;
use YellowTwins\FluidLens\Consistency\Check\JsFrameworkCheck;
use YellowTwins\FluidLens\Consistency\Check\LazyLoadCheck;
use YellowTwins\FluidLens\Consistency\Check\LightboxCheck;
use YellowTwins\FluidLens\Consistency\Check\LinkApproachCheck;
use YellowTwins\FluidLens\Consistency\Check\MapLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\NamespaceStyleCheck;
use YellowTwins\FluidLens\Consistency\Check\RenderStyleCheck;
use YellowTwins\FluidLens\Consistency\Check\SliderLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\TooltipLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\TranslateStyleCheck;
use YellowTwins\FluidLens\Consistency\Check\VideoPlayerCheck;
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
            new CssFrameworkCheck(),
            new JsFrameworkCheck(),
            new LightboxCheck(),
            new AnimationCheck(),
            new LazyLoadCheck(),
            new MapLibraryCheck(),
            new VideoPlayerCheck(),
            new GridLayoutCheck(),
            new TooltipLibraryCheck(),
            new CookieConsentCheck(),
            new NamespaceStyleCheck(),
            new RenderStyleCheck(),
            new TranslateStyleCheck(),
            new ImageApproachCheck(),
            new LinkApproachCheck(),
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
