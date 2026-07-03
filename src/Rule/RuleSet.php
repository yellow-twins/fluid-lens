<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

use YellowTwins\FluidLens\Rule\BestPractice\InlineStyleRule;
use YellowTwins\FluidLens\Rule\BestPractice\InlineSvgRule;
use YellowTwins\FluidLens\Rule\BestPractice\PreferFluidImageRule;
use YellowTwins\FluidLens\Rule\BestPractice\TargetBlankRelRule;
use YellowTwins\FluidLens\Rule\Markup\PictureImgRule;
use YellowTwins\FluidLens\Rule\Markup\PictureSourceSrcsetRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaAttributeRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaExpandedRoleRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaHiddenFocusableRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaRoleRule;
use YellowTwins\FluidLens\Rule\Wcag\ButtonNameRule;
use YellowTwins\FluidLens\Rule\Wcag\DuplicateIdRule;
use YellowTwins\FluidLens\Rule\Wcag\EmptyHeadingRule;
use YellowTwins\FluidLens\Rule\Wcag\FormLabelRule;
use YellowTwins\FluidLens\Rule\Wcag\HeadingOrderRule;
use YellowTwins\FluidLens\Rule\Wcag\HtmlLangRule;
use YellowTwins\FluidLens\Rule\Wcag\IframeTitleRule;
use YellowTwins\FluidLens\Rule\Wcag\ImageAltRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkNameRule;
use YellowTwins\FluidLens\Rule\Wcag\MediaAutoplayRule;
use YellowTwins\FluidLens\Rule\Wcag\MetaViewportRule;
use YellowTwins\FluidLens\Rule\Wcag\PositiveTabindexRule;
use YellowTwins\FluidLens\Rule\Wcag\TableHeaderRule;
use YellowTwins\FluidLens\Rule\Wcag\TabSelectedRule;
use YellowTwins\FluidLens\Rule\Wcag\TablistTabRule;

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
            new AriaExpandedRoleRule(),
            new TabSelectedRule(),
            new TablistTabRule(),
            new IframeTitleRule(),
            new MediaAutoplayRule(),
            new MetaViewportRule(),
            new DuplicateIdRule(),
            new HeadingOrderRule(),
            new EmptyHeadingRule(),
            new InlineStyleRule(),
            new InlineSvgRule(),
            new PreferFluidImageRule(),
            new TargetBlankRelRule(),
            new PictureImgRule(),
            new PictureSourceSrcsetRule(),
        ];
    }
}
