<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

/**
 * Recognises well-known slider/carousel libraries by their signature CSS classes.
 *
 * The class roots below are the stable, documented container/element classes each
 * library renders (matched as a whole token or a `root-`/`root__` prefix), so a
 * template's markup reveals which slider it uses.
 */
final class SliderLibrary
{
    /**
     * @var array<string, list<string>>
     */
    private const LIBRARIES = [
        'Swiper' => ['swiper'],
        'Slick' => ['slick'],
        'Glide' => ['glide'],
        'Splide' => ['splide'],
        'Owl Carousel' => ['owl-carousel'],
        'Flickity' => ['flickity'],
        'Keen Slider' => ['keen-slider'],
        'Tiny Slider' => ['tns', 'tiny-slider'],
    ];

    /**
     * The library a single CSS class token belongs to, or null.
     */
    public static function match(string $token): ?string
    {
        foreach (self::LIBRARIES as $name => $roots) {
            foreach ($roots as $root) {
                if ($token === $root || str_starts_with($token, $root . '-') || str_starts_with($token, $root . '__')) {
                    return $name;
                }
            }
        }

        return null;
    }
}
