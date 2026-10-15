<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Baseline;

use YellowTwins\FluidLens\Component\Model\ComponentCandidate;

/**
 * Accepted component suggestions, so a project can adopt the components command
 * and only hear about new candidates from then on.
 *
 * A suggestion is known by its identity (the Partial file, or the structure of
 * the copied markup) and its number of call sites. It stays quiet while it does
 * not grow; one more copy or render call brings it back.
 */
final class ComponentBaseline
{
    private const VERSION = 1;

    /**
     * @param array<string, int> $components identity => baselined call sites
     */
    public function __construct(
        private readonly array $components,
    ) {
    }

    /**
     * @param list<ComponentCandidate> $candidates
     */
    public static function fromCandidates(array $candidates): self
    {
        $components = [];
        foreach ($candidates as $candidate) {
            $components[$candidate->id] = max($components[$candidate->id] ?? 0, $candidate->callSites());
        }
        ksort($components);

        return new self($components);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{components?: array<string, int>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['components'] ?? []);
    }

    public function toJson(): string
    {
        return json_encode(
            ['version' => self::VERSION, 'components' => $this->components],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    public function count(): int
    {
        return count($this->components);
    }

    /**
     * @param list<ComponentCandidate> $candidates
     *
     * @return list<ComponentCandidate>
     */
    public function filter(array $candidates): array
    {
        return array_values(array_filter(
            $candidates,
            fn (ComponentCandidate $candidate): bool => !$this->covers($candidate),
        ));
    }

    private function covers(ComponentCandidate $candidate): bool
    {
        return isset($this->components[$candidate->id])
            && $candidate->callSites() <= $this->components[$candidate->id];
    }
}
