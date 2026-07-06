<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which mapping library the project embeds. Each ships a heavy tile
 * renderer, so mixing two on one site is rarely intended.
 */
final class MapLibraryCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'maps';
    }

    public function title(): string
    {
        return 'Map libraries';
    }

    protected function catalog(): array
    {
        return [
            'Leaflet' => ['leaflet'],
            'Mapbox GL' => ['mapboxgl'],
            'OpenLayers' => ['ol-viewport', 'ol-map'],
            'Google Maps' => ['gm-style', 'gm-err-container'],
        ];
    }
}
