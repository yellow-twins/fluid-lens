<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Detector\NearDuplicateCluster;
use YellowTwins\FluidLens\Detector\NearDuplicateMember;

/**
 * Serialises near-duplicate clusters to stable, machine-readable JSON.
 */
final class JsonNearDuplicateReporter
{
    private readonly StructurePreview $preview;

    public function __construct(?StructurePreview $preview = null)
    {
        $this->preview = $preview ?? new StructurePreview();
    }

    /**
     * @param list<NearDuplicateCluster> $clusters
     */
    public function render(array $clusters, int $filesScanned): string
    {
        $payload = [
            'filesScanned' => $filesScanned,
            'clusterCount' => count($clusters),
            'clusters' => array_map(fn (NearDuplicateCluster $cluster): array => $this->cluster($cluster), $clusters),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cluster(NearDuplicateCluster $cluster): array
    {
        return [
            'similarity' => round($cluster->similarity, 4),
            'memberCount' => $cluster->memberCount(),
            'totalOccurrences' => $cluster->totalOccurrences(),
            'variants' => array_map(
                fn (NearDuplicateMember $member): array => $this->member($member),
                $cluster->members,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function member(NearDuplicateMember $member): array
    {
        return [
            'elementCount' => $member->elementCount,
            'structure' => $this->preview->render($member->representative),
            'occurrences' => array_map(
                static fn ($occurrence): array => ['file' => $occurrence->file, 'line' => $occurrence->line],
                $member->occurrences,
            ),
        ];
    }
}
