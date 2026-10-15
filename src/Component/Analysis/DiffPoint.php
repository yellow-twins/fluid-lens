<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Parser\Node;

/**
 * One place where copies of the same markup differ, with the value each copy
 * has there (null when a copy lacks the attribute).
 */
final class DiffPoint
{
    public const ATTRIBUTE = 'attribute';
    public const CLASS_TOKENS = 'class';
    public const TEXT = 'text';

    /**
     * @param Node                $node      the node in the copy the draft is written from
     * @param list<string|null>   $values    one value per copy, in occurrence order
     * @param string|null         $attribute the attribute name for attribute and class differences
     */
    public function __construct(
        public readonly string $kind,
        public readonly Node $node,
        public readonly array $values,
        public readonly ?string $attribute = null,
    ) {
    }

    public function isMissingSomewhere(): bool
    {
        return in_array(null, $this->values, true);
    }
}
