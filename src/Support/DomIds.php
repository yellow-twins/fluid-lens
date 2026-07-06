<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Collects the static `id` values declared in a template, so a rule can tell
 * whether an ARIA reference (`aria-controls`, `aria-labelledby`, …) points at an
 * element that exists in the same file. Dynamic ids ({@code {expr}}) are skipped
 * because their runtime value cannot be judged statically.
 */
final class DomIds
{
    /**
     * @return array<string, true>
     */
    public static function declaredIn(Node $tree): array
    {
        $ids = [];
        foreach (Elements::all($tree) as $element) {
            $id = $element->attribute('id');
            if ($id !== null && trim($id) !== '' && !Attributes::isDynamic($id)) {
                $ids[trim($id)] = true;
            }
        }

        return $ids;
    }
}
