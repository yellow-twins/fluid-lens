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
use YellowTwins\FluidLens\Rule\Wcag\AbbrTitleRule;
use YellowTwins\FluidLens\Rule\Wcag\AltFilenameRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaBooleanRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaRoleRule;
use YellowTwins\FluidLens\Rule\Wcag\DirValidRule;
use YellowTwins\FluidLens\Rule\Wcag\LabelEmptyRule;
use YellowTwins\FluidLens\Rule\Wcag\LangValidRule;
use YellowTwins\FluidLens\Rule\Wcag\MetaRefreshRule;
use YellowTwins\FluidLens\Rule\Wcag\ScopeValueRule;
use YellowTwins\FluidLens\Rule\Wcag\SummaryDetailsRule;
use YellowTwins\FluidLens\Rule\Wcag\ButtonNameRule;
use YellowTwins\FluidLens\Rule\Wcag\DuplicateIdRule;
use YellowTwins\FluidLens\Rule\Wcag\EmptyHeadingRule;
use YellowTwins\FluidLens\Rule\Wcag\FieldsetLegendRule;
use YellowTwins\FluidLens\Rule\Wcag\FormLabelRule;
use YellowTwins\FluidLens\Rule\Wcag\HeadingOrderRule;
use YellowTwins\FluidLens\Rule\Wcag\HtmlLangRule;
use YellowTwins\FluidLens\Rule\Wcag\IframeTitleRule;
use YellowTwins\FluidLens\Rule\Wcag\ImageAltRule;
use YellowTwins\FluidLens\Rule\Wcag\InputImageAltRule;
use YellowTwins\FluidLens\Rule\Wcag\LabelForRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkGenericTextRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkNameRule;
use YellowTwins\FluidLens\Rule\Wcag\ListStructureRule;
use YellowTwins\FluidLens\Rule\Wcag\MarqueeBlinkRule;
use YellowTwins\FluidLens\Rule\Wcag\MediaAutoplayRule;
use YellowTwins\FluidLens\Rule\Wcag\MetaViewportRule;
use YellowTwins\FluidLens\Rule\Wcag\NestedInteractiveRule;
use YellowTwins\FluidLens\Rule\Wcag\PositiveTabindexRule;
use YellowTwins\FluidLens\Rule\Wcag\RoleRequiredAttrRule;
use YellowTwins\FluidLens\Rule\Wcag\TableHeaderRule;
use YellowTwins\FluidLens\Rule\Wcag\TabSelectedRule;
use YellowTwins\FluidLens\Rule\Wcag\TablistTabRule;
use YellowTwins\FluidLens\Rule\Wcag\ThEmptyRule;
use YellowTwins\FluidLens\Rule\Wcag\VideoCaptionsRule;

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
            new LabelForRule(),
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
            new AltFilenameRule(),
            new InputImageAltRule(),
            new LinkGenericTextRule(),
            new NestedInteractiveRule(),
            new FieldsetLegendRule(),
            new ListStructureRule(),
            new ThEmptyRule(),
            new RoleRequiredAttrRule(),
            new VideoCaptionsRule(),
            new MarqueeBlinkRule(),
            new LangValidRule(),
            new LabelEmptyRule(),
            new AriaBooleanRule(),
            new DirValidRule(),
            new MetaRefreshRule(),
            new SummaryDetailsRule(),
            new ScopeValueRule(),
            new AbbrTitleRule(),
            new InlineStyleRule(),
            new InlineSvgRule(),
            new PreferFluidImageRule(),
            new TargetBlankRelRule(),
            new PictureImgRule(),
            new PictureSourceSrcsetRule(),
        ];
    }
}
