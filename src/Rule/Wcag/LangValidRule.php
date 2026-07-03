<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A `lang` attribute must hold a valid BCP 47 language tag ("de", "en-US"),
 * not a language name like "german".
 *
 * WCAG 3.1.1 Language of Page / 3.1.2 Language of Parts (Level A/AA).
 */
final class LangValidRule extends AbstractElementRule
{
    private const TAG = '/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{1,8})*$/';

    public function name(): string
    {
        return 'wcag.lang-valid';
    }

    protected function inspect(Node $element, string $file): array
    {
        $lang = $element->attribute('lang');
        if ($lang === null || $lang === '' || Attributes::isDynamic($lang) || preg_match(self::TAG, $lang) === 1) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('Invalid lang value "%s"; use a BCP 47 tag like "de" or "en-US".', $lang),
                $file,
                'WCAG 3.1.1 (A)',
            ),
        ];
    }
}
