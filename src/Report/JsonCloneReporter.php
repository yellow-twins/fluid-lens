<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Detector\CloneGroup;

/**
 * Serialises clone groups to stable, machine-readable JSON for CI pipelines and
 * for AI assistants to consume.
 */
final class JsonCloneReporter
{
    private readonly StructurePreview $preview;

    public function __construct(?StructurePreview $preview = null)
    {
        $this->preview = $preview ?? new StructurePreview();
    }

    /**
     * @param list<CloneGroup> $groups
     */
    public function render(array $groups, int $filesScanned): string
    {
        $payload = [
            'filesScanned' => $filesScanned,
            'groupCount' => count($groups),
            'groups' => array_map(fn (CloneGroup $group): array => $this->group($group), $groups),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function group(CloneGroup $group): array
    {
        return [
            'hash' => $group->hash,
            'elementCount' => $group->elementCount,
            'occurrenceCount' => $group->occurrenceCount(),
            'structure' => $this->preview->render($group->representative()),
            'occurrences' => array_map(
                static fn ($occurrence): array => ['file' => $occurrence->file, 'line' => $occurrence->line],
                $group->occurrences,
            ),
        ];
    }
}
