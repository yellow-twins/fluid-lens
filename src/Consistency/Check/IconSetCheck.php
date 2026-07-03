<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\ClassSignatureCheck;

/**
 * Detects which icon sets the project uses; mixing several bloats the page and
 * fragments the visual language.
 */
final class IconSetCheck extends ClassSignatureCheck
{
    public function name(): string
    {
        return 'icons';
    }

    public function title(): string
    {
        return 'Icon sets';
    }

    protected function catalog(): array
    {
        return [
            'Font Awesome' => ['fa', 'fas', 'far', 'fab', 'fal', 'fad'],
            'Bootstrap Icons' => ['bi'],
            'Material Icons' => ['material-icons', 'material-symbols'],
            'Ionicons' => ['ion', 'ion-icon'],
            'Feather' => ['feather'],
            'Remix Icon' => ['ri'],
            'Boxicons' => ['bx', 'bxs', 'bxl'],
        ];
    }
}
