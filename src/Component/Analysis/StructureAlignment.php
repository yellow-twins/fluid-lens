<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Parser\Node;

/**
 * How similar copies line up with the first one: the values that differ, the
 * elements only some copies have, and the region that differs too much and
 * becomes the slot.
 */
final class StructureAlignment
{
    /**
     * @param list<DiffPoint>                                       $points
     * @param list<array{node: Node, present: list<bool>}>          $presence elements of the first copy
     *                                                                        missing in some copies
     * @param Node|null                                             $slot     the element of the first copy
     *                                                                        whose content becomes the slot
     * @param list<Node|null>                                       $slotNodes the matching element per copy
     */
    public function __construct(
        public readonly array $points,
        public readonly array $presence = [],
        public readonly ?Node $slot = null,
        public readonly array $slotNodes = [],
    ) {
    }
}
