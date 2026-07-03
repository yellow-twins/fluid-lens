<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\ClassSignatureCheck;

/**
 * Detects which CSS frameworks the project uses. Mixing several (the classic
 * legacy-Bootstrap-plus-new-Tailwind situation) doubles the CSS payload and the
 * mental overhead.
 *
 * Only distinctive signatures are used — the responsive grid and `d-*` utilities
 * for Bootstrap, variant prefixes and the numeric colour scale for Tailwind — so
 * generic utility names shared between frameworks do not cause false positives.
 */
final class CssFrameworkCheck extends ClassSignatureCheck
{
    public function name(): string
    {
        return 'css';
    }

    public function title(): string
    {
        return 'CSS frameworks';
    }

    protected function catalog(): array
    {
        return [
            'Bootstrap' => [
                'col-sm', 'col-md', 'col-lg', 'col-xl', 'col-xxl',
                'navbar', 'd-flex', 'd-none', 'd-block', 'd-inline',
            ],
            'Tailwind' => [
                '/^(sm|md|lg|xl|2xl):/',
                '/^(hover|focus|active|disabled|group-hover|dark):/',
                '/^(bg|text|border|from|via|to)-[a-z]+-\d{2,3}$/',
            ],
            'Bulma' => [
                'columns', 'has-text-centered', 'navbar-burger',
                '/^is-(primary|info|success|warning|danger|link|light|dark|fullwidth)$/',
            ],
            'Foundation' => ['grid-x', 'grid-y', 'callout', 'top-bar', 'button-group'],
        ];
    }
}
