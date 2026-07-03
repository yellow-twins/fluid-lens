<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

use YellowTwins\FluidLens\Rule\BestPractice\InlineStyleRule;
use YellowTwins\FluidLens\Rule\BestPractice\InlineSvgRule;
use YellowTwins\FluidLens\Rule\BestPractice\PreferFluidImageRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaAttributeRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaHiddenFocusableRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaRoleRule;
use YellowTwins\FluidLens\Rule\Wcag\ButtonNameRule;
use YellowTwins\FluidLens\Rule\Wcag\DuplicateIdRule;
use YellowTwins\FluidLens\Rule\Wcag\FormLabelRule;
use YellowTwins\FluidLens\Rule\Wcag\HeadingOrderRule;
use YellowTwins\FluidLens\Rule\Wcag\HtmlLangRule;
use YellowTwins\FluidLens\Rule\Wcag\IframeTitleRule;
use YellowTwins\FluidLens\Rule\Wcag\ImageAltRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkNameRule;
use YellowTwins\FluidLens\Rule\Wcag\MediaAutoplayRule;
use YellowTwins\FluidLens\Rule\Wcag\PositiveTabindexRule;
use YellowTwins\FluidLens\Rule\Wcag\TableHeaderRule;

/**
 * The built-in rules, covering the statically decidable WCAG (up to AAA) markup
 * checks plus a few Fluid best-practice sniffs.
 */
final class RuleSet
{
    /**
     * @return list<Rule>
     */
    public static function default(): array
    {
        return [
            new ImageAltRule(),
            new LinkNameRule(),
            new ButtonNameRule(),
            new FormLabelRule(),
            new HtmlLangRule(),
            new PositiveTabindexRule(),
            new TableHeaderRule(),
            new AriaRoleRule(),
            new AriaAttributeRule(),
            new AriaHiddenFocusableRule(),
            new IframeTitleRule(),
            new MediaAutoplayRule(),
            new DuplicateIdRule(),
            new HeadingOrderRule(),
            new InlineStyleRule(),
            new InlineSvgRule(),
            new PreferFluidImageRule(),
        ];
    }
}
