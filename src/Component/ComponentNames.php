<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\ComponentIndex;

/**
 * Hands out component names that clash neither with existing components nor
 * with other suggestions of the same run.
 */
final class ComponentNames
{
    /** @var array<string, true> lowercase names already handed out */
    private array $claimed = [];

    public function __construct(
        private readonly ComponentIndex $components,
    ) {
    }

    public function claim(string $name): string
    {
        $candidate = $name;
        for ($suffix = 2; $this->isTaken($candidate); $suffix++) {
            $candidate = $name . $suffix;
        }

        $this->claimed[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * Claims the first free name of the list, numbering the first one when all
     * are taken.
     *
     * @param non-empty-list<string> $options
     */
    public function claimFirst(array $options): string
    {
        foreach ($options as $option) {
            if (!$this->isTaken($option)) {
                return $this->claim($option);
            }
        }

        return $this->claim($options[0]);
    }

    private function isTaken(string $name): bool
    {
        return isset($this->claimed[strtolower($name)]) || $this->components->hasComponent(lcfirst($name));
    }
}
