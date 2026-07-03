<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\ClassSignatureCheck;

/**
 * Detects which slider/carousel libraries the project uses.
 */
final class SliderLibraryCheck extends ClassSignatureCheck
{
    public function name(): string
    {
        return 'sliders';
    }

    public function title(): string
    {
        return 'Slider libraries';
    }

    protected function catalog(): array
    {
        return [
            'Swiper' => ['swiper'],
            'Slick' => ['slick'],
            'Glide' => ['glide'],
            'Splide' => ['splide'],
            'Owl Carousel' => ['owl-carousel'],
            'Flickity' => ['flickity'],
            'Keen Slider' => ['keen-slider'],
            'Tiny Slider' => ['tns', 'tiny-slider'],
        ];
    }
}
