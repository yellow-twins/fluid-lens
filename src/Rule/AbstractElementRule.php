<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Base for rules that judge each element on its own. Subclasses implement
 * {@see inspect()} for a single element; the traversal is handled here.
 */
abstract class AbstractElementRule implements Rule
{
    public function check(ParsedTemplate $template): array
    {
        $findings = [];
        foreach (Elements::all($template->tree) as $element) {
            foreach ($this->inspect($element, $template->file) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    abstract protected function inspect(Node $element, string $file): array;

    protected function finding(
        Node $element,
        Severity $severity,
        string $message,
        string $file,
        ?string $reference = null,
    ): Finding {
        $line = $element->sourceRange?->startLine ?? 0;

        return new Finding($this->name(), $severity, $message, $file, $line, $reference);
    }
}
