<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Fluid\Bindings;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Lines up similar — not identical — copies with the first one.
 *
 * Children are matched by their structural signature as a longest common
 * subsequence, so an element added or removed in one copy does not shift the
 * rest. What remains unmatched decides the shape of the component:
 *
 * - an element some copies simply omit becomes an optional element, switched
 *   on and off by a boolean argument;
 * - anywhere else the copies diverge, the enclosing element's content becomes
 *   the component's slot. Fluid components have one default slot, so all such
 *   regions must share a common element below the root — otherwise the copies
 *   are too different to be one component.
 */
final class StructureAligner
{
    /** @var array<int, Node> first-copy nodes by id */
    private array $nodes = [];

    /** @var array<int, array<int, Node>> first-copy node id => copy => matching node */
    private array $matches = [];

    /** @var array<int, array<int, true>> first-copy element id => copies with extra children there */
    private array $extras = [];

    private LocalRenames $renames;

    private int $copies = 0;

    public function __construct()
    {
        $this->renames = new LocalRenames();
    }

    /**
     * @param non-empty-list<Node> $roots the root of each copy; the first one is drafted from
     *
     * @return StructureAlignment|null null when the copies diverge right at the root
     */
    public function align(array $roots): ?StructureAlignment
    {
        $this->nodes = [];
        $this->matches = [];
        $this->extras = [];
        $this->renames = new LocalRenames();
        $this->copies = count($roots);

        $first = $roots[0];
        foreach ($roots as $copy => $root) {
            if ($root->name !== $first->name) {
                return null;
            }
            $this->match($first, $root, $copy);
        }

        $slotParents = [];
        $presence = [];
        foreach ($this->nodes as $id => $node) {
            if (isset($this->extras[$id])) {
                $slotParents[] = $node;
            }
            $this->classifyMissingChildren($node, $slotParents, $presence);
        }

        $slot = $this->lowestCommonAncestor($slotParents);
        if ($slot === $first) {
            return null;
        }

        $presence = array_values(array_filter(
            $presence,
            fn (array $entry): bool => $slot === null || !$this->isInside($entry['node'], $slot),
        ));

        return new StructureAlignment(
            $this->differences($slot, $presence),
            $presence,
            $slot,
            $slot === null ? [] : $this->slotNodes($slot),
        );
    }

    private function match(Node $first, Node $other, int $copy): void
    {
        $id = spl_object_id($first);
        $this->nodes[$id] = $first;
        $this->matches[$id][$copy] = $other;

        if (!$first->isElement()) {
            return;
        }

        foreach (Bindings::attributesOf($first->name) as $attribute) {
            $this->renames->record([0 => $first->attribute($attribute), $copy => $other->attribute($attribute)]);
        }

        $mine = $this->significantChildren($first);
        $theirs = $this->significantChildren($other);
        $pairs = $this->longestCommonSubsequence($mine, $theirs);

        $matchedTheirs = [];
        foreach ($pairs as [$a, $b]) {
            $this->match($mine[$a], $theirs[$b], $copy);
            $matchedTheirs[$b] = true;
        }

        if (count($matchedTheirs) < count($theirs)) {
            $this->extras[$id][$copy] = true;
        }
    }

    /**
     * Children of the first copy that some copies lack: a single element that is
     * merely left out becomes optional, anything else marks the parent as a slot.
     *
     * @param list<Node>                                    $slotParents
     * @param list<array{node: Node, present: list<bool>}> $presence
     */
    private function classifyMissingChildren(Node $parent, array &$slotParents, array &$presence): void
    {
        if (!$parent->isElement()) {
            return;
        }

        $parentId = spl_object_id($parent);
        foreach ($this->significantChildren($parent) as $child) {
            $present = [];
            $omittedOnly = true;
            for ($copy = 0; $copy < $this->copies; $copy++) {
                $present[$copy] = isset($this->matches[spl_object_id($child)][$copy]);
                $parentPresent = isset($this->matches[$parentId][$copy]);
                if (!$present[$copy] && $parentPresent && isset($this->extras[$parentId][$copy])) {
                    $omittedOnly = false;
                }
            }

            if (!in_array(false, $present, true)) {
                continue;
            }

            if ($omittedOnly && $child->isElement()) {
                $presence[] = ['node' => $child, 'present' => $present];
            } else {
                $slotParents[] = $parent;
            }
        }
    }

    /**
     * @param list<array{node: Node, present: list<bool>}> $presence
     *
     * @return list<DiffPoint>
     */
    private function differences(?Node $slot, array $presence): array
    {
        $points = [];
        foreach ($this->nodes as $id => $node) {
            if ($slot !== null && $node !== $slot && $this->isInside($node, $slot)) {
                continue;
            }

            if ($node->type === NodeType::Text) {
                $this->compare($id, $node, null, $points);
                continue;
            }

            if (!$node->isElement()) {
                continue;
            }

            $names = [];
            foreach ($this->matches[$id] as $match) {
                foreach (array_keys($match->attributes) as $name) {
                    $names[(string) $name] = true;
                }
            }

            foreach (array_keys($names) as $name) {
                if (!Bindings::isBinding($node->name, (string) $name)) {
                    $this->compare($id, $node, (string) $name, $points);
                }
            }
        }

        return $points;
    }

    /**
     * @param list<DiffPoint> $points
     */
    private function compare(int $id, Node $node, ?string $attribute, array &$points): void
    {
        $values = [];
        $compared = [];
        for ($copy = 0; $copy < $this->copies; $copy++) {
            $match = $this->matches[$id][$copy] ?? null;
            $value = match (true) {
                $match === null => null,
                $attribute === null => $this->text($match),
                default => $match->attribute($attribute),
            };
            $values[$copy] = $value;
            if ($match !== null) {
                $compared[] = $value === null ? "\0" : $this->renames->apply($copy, $value);
            }
        }

        if (count(array_unique($compared)) <= 1) {
            return;
        }

        $kind = match (true) {
            $attribute === null => DiffPoint::TEXT,
            $attribute === 'class' => DiffPoint::CLASS_TOKENS,
            default => DiffPoint::ATTRIBUTE,
        };
        $points[] = new DiffPoint($kind, $node, $values, $attribute);
    }

    /**
     * @return list<Node|null>
     */
    private function slotNodes(Node $slot): array
    {
        $nodes = [];
        for ($copy = 0; $copy < $this->copies; $copy++) {
            $nodes[] = $this->matches[spl_object_id($slot)][$copy] ?? null;
        }

        return $nodes;
    }

    /**
     * @param list<Node> $nodes
     */
    private function lowestCommonAncestor(array $nodes): ?Node
    {
        $common = null;
        foreach ($nodes as $node) {
            $chain = $this->ancestorsAndSelf($node);
            $common = $common === null
                ? $chain
                : array_values(array_filter($common, static fn (Node $n): bool => in_array($n, $chain, true)));
        }

        return $common === null || $common === [] ? null : $common[0];
    }

    /**
     * @return list<Node> nearest first
     */
    private function ancestorsAndSelf(Node $node): array
    {
        $chain = [];
        for ($current = $node; $current !== null; $current = $current->parent()) {
            $chain[] = $current;
        }

        return $chain;
    }

    private function isInside(Node $node, Node $ancestor): bool
    {
        for ($current = $node->parent(); $current !== null; $current = $current->parent()) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Index pairs of matching children, in order.
     *
     * @param list<Node> $left
     * @param list<Node> $right
     *
     * @return list<array{0: int, 1: int}>
     */
    private function longestCommonSubsequence(array $left, array $right): array
    {
        $a = array_map(fn (Node $node): string => $this->signature($node), $left);
        $b = array_map(fn (Node $node): string => $this->signature($node), $right);
        $rows = count($a);
        $columns = count($b);

        $length = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));
        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $length[$i][$j] = $a[$i] === $b[$j]
                    ? $length[$i + 1][$j + 1] + 1
                    : max($length[$i + 1][$j], $length[$i][$j + 1]);
            }
        }

        $pairs = [];
        for ($i = 0, $j = 0; $i < $rows && $j < $columns;) {
            if ($a[$i] === $b[$j]) {
                $pairs[] = [$i, $j];
                $i++;
                $j++;
            } elseif ($length[$i + 1][$j] >= $length[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $pairs;
    }

    /**
     * Children are matched by tag name only: an attribute one copy adds becomes
     * an optional argument, it does not make the element a different one.
     */
    private function signature(Node $node): string
    {
        return $node->isElement() ? $node->name : '#t';
    }

    private function text(Node $node): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $node->text));
    }

    /**
     * @return list<Node>
     */
    private function significantChildren(Node $node): array
    {
        return array_values(array_filter(
            $node->children(),
            static fn (Node $child): bool => $child->type !== NodeType::Comment,
        ));
    }
}
