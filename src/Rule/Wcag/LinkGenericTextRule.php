<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Text;

/**
 * Link text like "click here" or "read more" is meaningless out of context (and
 * screen-reader users often navigate by a list of links). Advisory, because an
 * `aria-label` can supply the context. Reported only for exact generic phrases.
 *
 * WCAG 2.4.4 Link Purpose (Level A).
 */
final class LinkGenericTextRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const GENERIC = ['click here', 'read more', 'learn more', 'more', 'here', 'details', 'link', 'read'];

    public function name(): string
    {
        return 'wcag.link-generic-text';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!$this->isLink($element) || Attributes::present($element, 'aria-label')) {
            return [];
        }

        $text = strtolower(Text::content($element));
        if (!in_array($text, self::GENERIC, true)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                sprintf('Link text "%s" is not descriptive on its own.', $text),
                $file,
                'WCAG 2.4.4 (A)',
            ),
        ];
    }

    private function isLink(Node $element): bool
    {
        return $element->name === 'a' || str_contains($element->name, 'link.');
    }
}
