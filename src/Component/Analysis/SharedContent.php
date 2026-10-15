<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Support\Elements;

/**
 * How much of their content copies share: the share of attribute values and
 * texts that are the same in every copy.
 *
 * Copies that share little more than their shape — three divs with different
 * classes and texts — are a coincidence of markup, not a component. A class
 * attribute counts by its tokens: "normal-link yellow" and "normal-link purple"
 * are half the same. An optional element counts as one difference, the slot as
 * all of its content.
 */
final class SharedContent
{
    public function share(Node $root, StructureAlignment $alignment): float
    {
        $differing = count($alignment->presence);
        foreach ($alignment->points as $point) {
            $differing += $point->kind === DiffPoint::CLASS_TOKENS ? $this->classDifference($point) : 1.0;
        }

        if ($alignment->slot !== null) {
            $differing += max(1, $this->slots($alignment->slot) - count($alignment->slot->attributes));
        }

        return max(0.0, 1 - $differing / max(1, $this->slots($root)));
    }

    /**
     * Attribute values and texts — the places where copies can differ.
     */
    public function slots(Node $root): int
    {
        $slots = 0;
        foreach (Elements::all($root) as $element) {
            $slots += count($element->attributes);
            foreach ($element->children() as $child) {
                if ($child->type === NodeType::Text) {
                    $slots++;
                }
            }
        }

        return $slots;
    }

    /**
     * The share of class names not every copy has.
     */
    private function classDifference(DiffPoint $point): float
    {
        $all = [];
        $tokens = [];
        foreach ($point->values as $copy => $value) {
            $tokens[$copy] = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens[$copy] as $token) {
                $all[$token] = true;
            }
        }

        if ($all === []) {
            return 1.0;
        }

        $shared = array_keys($all);
        foreach ($tokens as $list) {
            $shared = array_intersect($shared, $list);
        }

        return 1 - count($shared) / count($all);
    }
}
