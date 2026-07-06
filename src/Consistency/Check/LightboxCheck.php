<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which lightbox / gallery library the project uses, by class or the
 * attribute that wires it up (for example `data-fancybox`).
 */
final class LightboxCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'lightbox';
    }

    public function title(): string
    {
        return 'Lightbox libraries';
    }

    protected function catalog(): array
    {
        return [
            'Fancybox' => ['fancybox', 'data-fancybox'],
            'GLightbox' => ['glightbox'],
            'Magnific Popup' => ['magnific-popup', 'mfp'],
            'Lightgallery' => ['lightgallery', 'data-lg-size'],
            'PhotoSwipe' => ['pswp'],
            'Lightbox2' => ['data-lightbox'],
        ];
    }
}
