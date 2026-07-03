<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * An `<iframe>` needs a `title` so assistive technology can announce what the
 * embedded content is (a video, a map, …).
 *
 * WCAG 4.1.2 Name, Role, Value / 2.4.1 Bypass Blocks (Level A).
 */
final class IframeTitleRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const NAMING_ATTRIBUTES = ['title', 'aria-label', 'aria-labelledby'];

    public function name(): string
    {
        return 'wcag.iframe-title';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'iframe') {
            return [];
        }

        foreach (self::NAMING_ATTRIBUTES as $attribute) {
            if (Attributes::present($element, $attribute)) {
                return [];
            }
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                '<iframe> has no title describing its embedded content.',
                $file,
                'WCAG 4.1.2 (A)',
            ),
        ];
    }
}
