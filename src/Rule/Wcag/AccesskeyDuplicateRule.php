<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Two elements sharing the same `accesskey` create a keyboard shortcut conflict:
 * the browser can only reach one of them. Dynamic values ({@code {expr}}) are
 * ignored.
 *
 * WCAG 2.1.1 Keyboard (Level A).
 */
final class AccesskeyDuplicateRule implements Rule
{
    public function name(): string
    {
        return 'wcag.accesskey-duplicate';
    }

    public function check(ParsedTemplate $template): array
    {
        $elements = Elements::all($template->tree);
        $counts = $this->countKeys($elements);

        $findings = [];
        foreach ($elements as $element) {
            $key = $this->keyOf($element);
            if ($key !== null && ($counts[$key] ?? 0) > 1) {
                $findings[] = new Finding(
                    $this->name(),
                    Severity::Warning,
                    sprintf('Duplicate accesskey "%s".', $key),
                    $template->file,
                    $element->sourceRange?->startLine ?? 0,
                    'WCAG 2.1.1 (A)',
                );
            }
        }

        return $findings;
    }

    /**
     * @param list<Node> $elements
     *
     * @return array<string, int>
     */
    private function countKeys(array $elements): array
    {
        $counts = [];
        foreach ($elements as $element) {
            $key = $this->keyOf($element);
            if ($key !== null) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function keyOf(Node $element): ?string
    {
        $key = $element->attribute('accesskey');
        if ($key === null || trim($key) === '' || Attributes::isDynamic($key)) {
            return null;
        }

        return strtolower(trim($key));
    }
}
