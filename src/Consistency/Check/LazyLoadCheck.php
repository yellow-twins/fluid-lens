<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which lazy-loading strategy the project uses: the native `loading`
 * attribute versus a JavaScript library. Mixing them means some images defer via
 * the browser and others via script, which is easy to get inconsistent.
 */
final class LazyLoadCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'lazyload';
    }

    public function title(): string
    {
        return 'Lazy-loading strategy';
    }

    protected function catalog(): array
    {
        return [
            'Native (loading attribute)' => ['loading'],
            'lazysizes' => ['lazyload', 'data-sizes'],
            'lozad' => ['lozad'],
            'vanilla-lazyload' => ['data-lazy'],
        ];
    }
}
