<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which JavaScript interaction frameworks the project sprinkles into its
 * markup, recognised by their attribute conventions. Mixing several is a smell:
 * each ships its own runtime and mental model.
 */
final class JsFrameworkCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'js-framework';
    }

    public function title(): string
    {
        return 'JS interaction frameworks';
    }

    protected function catalog(): array
    {
        return [
            'Alpine.js' => ['/^x-(data|show|bind|on|model|if|for|text|html|init)/'],
            'Vue' => ['/^v-(if|else|for|bind|on|model|show|html|text)/'],
            'htmx' => ['/^hx-/'],
            'Stimulus' => ['data-controller', '/^data-action$/'],
            'Turbo' => ['/^data-turbo/'],
        ];
    }
}
