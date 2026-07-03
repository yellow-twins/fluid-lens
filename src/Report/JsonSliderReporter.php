<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Consistency\SliderUsage;

/**
 * Serialises slider-library usage to stable, machine-readable JSON.
 */
final class JsonSliderReporter
{
    /**
     * @param list<SliderUsage> $usages
     */
    public function render(array $usages, int $filesScanned): string
    {
        $payload = [
            'filesScanned' => $filesScanned,
            'libraryCount' => count($usages),
            'consistent' => count($usages) <= 1,
            'libraries' => array_map(
                static fn (SliderUsage $usage): array => [
                    'library' => $usage->library,
                    'fileCount' => $usage->fileCount(),
                    'files' => $usage->files,
                ],
                $usages,
            ),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
