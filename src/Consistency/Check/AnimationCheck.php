<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which scroll/entrance animation library the project uses, by class
 * (Animate.css, WOW.js) or wiring attribute (AOS's `data-aos`).
 */
final class AnimationCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'animation';
    }

    public function title(): string
    {
        return 'Animation libraries';
    }

    protected function catalog(): array
    {
        return [
            'Animate.css' => ['animated', '/^animate__/'],
            'WOW.js' => ['wow'],
            'AOS' => ['data-aos', 'aos-init'],
        ];
    }
}
