<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * When an element carries both `lang` and `xml:lang`, the two must agree —
 * otherwise assistive technology gets contradictory language information.
 *
 * WCAG 3.1.1 / 3.1.2 Language of Page / of Parts (Level A / AA).
 */
final class LangXmlMismatchRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.lang-xml-mismatch';
    }

    protected function inspect(Node $element, string $file): array
    {
        $lang = $element->attribute('lang');
        $xmlLang = $element->attribute('xml:lang');
        if ($lang === null || $xmlLang === null) {
            return [];
        }

        if (Attributes::isDynamic($lang) || Attributes::isDynamic($xmlLang)) {
            return [];
        }

        if (strtolower(trim($lang)) === strtolower(trim($xmlLang))) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('lang="%s" and xml:lang="%s" disagree.', $lang, $xmlLang),
                $file,
                'WCAG 3.1.2 (AA)',
            ),
        ];
    }
}
