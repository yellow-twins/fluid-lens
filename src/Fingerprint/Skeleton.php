<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fingerprint;

/**
 * The structural fingerprint of a subtree: a hash that is equal for structurally
 * identical markup (ignoring classes, text and variable content) together with
 * the number of elements the subtree contains.
 */
final class Skeleton
{
    public function __construct(
        public readonly string $hash,
        public readonly int $elementCount,
    ) {
    }
}
