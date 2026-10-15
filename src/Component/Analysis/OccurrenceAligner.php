<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Fluid\Bindings;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Lines up the copies of an exactly duplicated structure and lists where they
 * differ.
 *
 * Exact clones share their structural fingerprint — the same elements, nesting
 * and attribute names — so they can be walked in lockstep. What remains are
 * attribute values, class names and text, which become the component's
 * arguments.
 *
 * Local variables are not differences: when one copy loops "as artist" and the
 * other "as resident", the second copy's references are read with the first
 * copy's name, so {resident.name} matches {artist.name}.
 */
final class OccurrenceAligner
{
    private LocalRenames $renames;

    public function __construct()
    {
        $this->renames = new LocalRenames();
    }

    /**
     * @param non-empty-list<Node> $roots the root of each copy; the first one is drafted from
     *
     * @return list<DiffPoint>|null null when the copies do not line up after all
     */
    public function align(array $roots): ?array
    {
        $points = [];
        $this->renames = new LocalRenames();

        return $this->alignNodes($roots, $points) ? $points : null;
    }

    /**
     * @param non-empty-list<Node> $nodes
     * @param list<DiffPoint>      $points
     */
    private function alignNodes(array $nodes, array &$points): bool
    {
        $first = $nodes[0];
        foreach ($nodes as $node) {
            if ($node->type !== $first->type || $node->name !== $first->name) {
                return false;
            }
        }

        if ($first->type === NodeType::Text) {
            $this->compareTexts($nodes, $points);

            return true;
        }

        $this->compareAttributes($nodes, $points);

        $children = array_map(fn (Node $node): array => $this->significantChildren($node), $nodes);
        $count = count($children[0]);
        foreach ($children as $list) {
            if (count($list) !== $count) {
                return false;
            }
        }

        for ($index = 0; $index < $count; $index++) {
            $column = array_map(static fn (array $list): Node => $list[$index], $children);
            if (!$this->alignNodes($column, $points)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param non-empty-list<Node> $nodes
     * @param list<DiffPoint>      $points
     */
    private function compareTexts(array $nodes, array &$points): void
    {
        $values = array_map(static fn (Node $node): string => self::normalise($node->text), $nodes);
        if (count(array_unique($this->renamed($values))) > 1) {
            $points[] = new DiffPoint(DiffPoint::TEXT, $nodes[0], array_map('trim', $values));
        }
    }

    /**
     * @param non-empty-list<Node> $nodes
     * @param list<DiffPoint>      $points
     */
    private function compareAttributes(array $nodes, array &$points): void
    {
        $names = [];
        foreach ($nodes as $node) {
            foreach (array_keys($node->attributes) as $name) {
                $names[(string) $name] = true;
            }
        }

        foreach (array_keys($names) as $name) {
            $name = (string) $name;
            $values = array_map(static fn (Node $node): ?string => $node->attribute($name), $nodes);
            if (Bindings::isBinding($nodes[0]->name, $name)) {
                $this->renames->record($values);
                continue;
            }

            $compared = $this->renamed(array_map(static fn (?string $v): string => $v ?? "\0", $values));
            if (count(array_unique($compared)) === 1) {
                continue;
            }

            $kind = $name === 'class' ? DiffPoint::CLASS_TOKENS : DiffPoint::ATTRIBUTE;
            $points[] = new DiffPoint($kind, $nodes[0], $values, $name);
        }
    }

    /**
     * Each copy's values with its local variables named as in the first copy.
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function renamed(array $values): array
    {
        foreach ($values as $copy => $value) {
            $values[$copy] = $this->renames->apply($copy, $value);
        }

        return $values;
    }

    /**
     * Children that count for the structure: comments are skipped like the
     * fingerprint skips them.
     *
     * @return list<Node>
     */
    private function significantChildren(Node $node): array
    {
        return array_values(array_filter(
            $node->children(),
            static fn (Node $child): bool => $child->type !== NodeType::Comment,
        ));
    }

    private static function normalise(string $text): string
    {
        return (string) preg_replace('/\s+/', ' ', $text);
    }
}
