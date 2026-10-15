<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * The part of the markup that differs too much between the copies to become an
 * argument; it becomes the component's default {@code <f:slot />}.
 */
final class SlotProposal
{
    /**
     * @param list<string> $variants a short preview of what each copy puts there
     */
    public function __construct(
        public readonly string $location,
        public readonly array $variants,
    ) {
    }
}
