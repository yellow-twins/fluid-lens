<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\ComponentIndex;
use YellowTwins\FluidLens\Component\Index\ExistingComponent;
use YellowTwins\FluidLens\Component\Model\ExistingComponentMatch;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Proposal\ComponentNamer;
use YellowTwins\FluidLens\Fingerprint\PqGramProfile;
use YellowTwins\FluidLens\Fingerprint\PqGramProfiler;
use YellowTwins\FluidLens\Parser\Node;

/**
 * Run-wide facts every suggestion needs: the existing components, which prefix
 * new components get and the names already handed out.
 */
final class DraftContext
{
    private const FALLBACK_PREFIX = 'my';

    /**
     * Below this pq-gram similarity a suggestion is not pointed at an existing
     * component.
     */
    private const MATCH_THRESHOLD = 0.8;

    public readonly ComponentNames $names;

    /** @var list<array{component: ExistingComponent, profile: PqGramProfile}>|null */
    private ?array $profiles = null;

    public function __construct(
        public readonly ComponentIndex $components,
        private readonly PqGramProfiler $profiler = new PqGramProfiler(),
    ) {
        $this->names = new ComponentNames($components);
    }

    /**
     * The existing component whose markup is most like the given block, when it
     * is close enough to use it instead of creating a new one.
     */
    public function existingMatch(Node $root): ?ExistingComponentMatch
    {
        $profile = $this->profiler->profile($root);
        $best = null;
        foreach ($this->definitionProfiles() as $entry) {
            $similarity = $this->profiler->similarity($profile, $entry['profile']);
            if ($similarity >= self::MATCH_THRESHOLD && ($best === null || $similarity > $best->similarity)) {
                $best = new ExistingComponentMatch(
                    $this->tag($entry['component']->name),
                    $entry['component']->definition->file,
                    $similarity,
                );
            }
        }

        return $best;
    }

    /**
     * @return list<array{component: ExistingComponent, profile: PqGramProfile}>
     */
    private function definitionProfiles(): array
    {
        if ($this->profiles !== null) {
            return $this->profiles;
        }

        $this->profiles = [];
        foreach ($this->components->components() as $component) {
            $body = PartialCandidateFactory::bodyElements($component->definition->tree);
            if (count($body) === 1) {
                $this->profiles[] = ['component' => $component, 'profile' => $this->profiler->profile($body[0])];
            }
        }

        return $this->profiles;
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
