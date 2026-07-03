<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Baseline;

use YellowTwins\FluidLens\Detector\CloneGroup;

/**
 * A record of already-known duplicated structures, so an established project can
 * adopt fluid-lens and only see *new* duplication from then on.
 *
 * A group is identified by its structure hash and the number of times it occurs.
 * A run's group is suppressed when the baseline already knows that structure with
 * at least as many occurrences; adding another occurrence surfaces it again.
 */
final class Baseline
{
    private const VERSION = 1;

    /**
     * @param array<string, int> $clones structure hash => baselined occurrence count
     */
    public function __construct(
        private readonly array $clones,
    ) {
    }

    /**
     * @param list<CloneGroup> $groups
     */
    public static function fromGroups(array $groups): self
    {
        $clones = [];
        foreach ($groups as $group) {
            $clones[$group->hash] = max($clones[$group->hash] ?? 0, $group->occurrenceCount());
        }

        return new self($clones);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{clones?: array<string, int>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['clones'] ?? []);
    }

    public function toJson(): string
    {
        return json_encode(
            ['version' => self::VERSION, 'clones' => $this->clones],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    public function count(): int
    {
        return count($this->clones);
    }

    /**
     * Returns only the groups that are not already covered by the baseline.
     *
     * @param list<CloneGroup> $groups
     *
     * @return list<CloneGroup>
     */
    public function filter(array $groups): array
    {
        return array_values(array_filter($groups, fn (CloneGroup $group): bool => !$this->covers($group)));
    }

    private function covers(CloneGroup $group): bool
    {
        return isset($this->clones[$group->hash]) && $group->occurrenceCount() <= $this->clones[$group->hash];
    }
}
