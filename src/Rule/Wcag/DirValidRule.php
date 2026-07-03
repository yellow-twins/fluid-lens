<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A `dir` attribute must be "ltr", "rtl" or "auto".
 *
 * WCAG 1.3.2 Meaningful Sequence (Level A).
 */
final class DirValidRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.dir-valid';
    }

    protected function inspect(Node $element, string $file): array
    {
        $dir = $element->attribute('dir');
        if ($dir === null || Attributes::isDynamic($dir) || in_array(strtolower($dir), ['ltr', 'rtl', 'auto'], true)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('Invalid dir value "%s"; use ltr, rtl or auto.', $dir),
                $file,
                'WCAG 1.3.2 (A)',
            ),
        ];
    }
}
