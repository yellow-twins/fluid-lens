<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * A `<ul>`/`<ol>` may only contain `<li>` children (plus `<script>`/`<template>`).
 * A stray `<div>` breaks the list semantics screen readers rely on. Fluid
 * ViewHelper children (which render the list items) are allowed.
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class ListStructureRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const ALLOWED = ['li', 'script', 'template'];

    public function name(): string
    {
        return 'wcag.list-structure';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'ul' && $element->name !== 'ol') {
            return [];
        }

        foreach ($element->elementChildren() as $child) {
            if ($this->isDisallowed($child)) {
                return [
                    $this->finding(
                        $element,
                        Severity::Warning,
                        sprintf('<%s> has a non-<li> child (<%s>).', $element->name, $child->name),
                        $file,
                        'WCAG 1.3.1 (A)',
                    ),
                ];
            }
        }

        return [];
    }

    private function isDisallowed(Node $child): bool
    {
        // ViewHelpers (name contains a namespace colon) render the list items.
        return !str_contains($child->name, ':') && !in_array($child->name, self::ALLOWED, true);
    }
}
