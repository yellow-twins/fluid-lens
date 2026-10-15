<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\ComponentIndex;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Proposal\ComponentNamer;

/**
 * Run-wide facts every suggestion needs: the existing components, which prefix
 * new components get and the names already handed out.
 */
final class DraftContext
{
    private const FALLBACK_PREFIX = 'my';

    public readonly ComponentNames $names;

    public function __construct(
        public readonly ComponentIndex $components,
    ) {
        $this->names = new ComponentNames($components);
    }

    public function prefix(): string
    {
        return $this->components->dominantPrefix() ?? self::FALLBACK_PREFIX;
    }

    public function tag(string $name): string
    {
        return ComponentNamer::tag($this->prefix(), $name);
    }

    /**
     * Prefix => PHP namespace of the component collection, for the draft wrapper.
     *
     * @return array<string, string>
     */
    public function collectionNamespace(): array
    {
        $prefix = $this->prefix();

        return [$prefix => $this->components->namespaceOf($prefix)];
    }

    /**
     * A caveat when the project has no component collection the new tag could use.
     */
    public function collectionCaveat(): ?CandidateNote
    {
        if ($this->components->dominantPrefix() !== null) {
            return null;
        }

        return new CandidateNote(
            CandidateNote::NO_COLLECTION,
            'no component collection found; register one (Configuration/Fluid/ComponentCollections.php)'
                . ' and import its namespace, "my:" is a placeholder',
        );
    }
}
