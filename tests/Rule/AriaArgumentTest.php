<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Wcag\AriaAttributeRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaHiddenFocusableRule;
use YellowTwins\FluidLens\Rule\Wcag\ButtonNameRule;
use YellowTwins\FluidLens\Rule\Wcag\FormLabelRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkNameRule;
use YellowTwins\FluidLens\Rule\Wcag\TabSelectedRule;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * TYPO3's tag-based ViewHelpers expand `aria="{…}"`, `data="{…}"` and
 * `additionalAttributes="{…}"` into real attributes. The rules must see them,
 * otherwise a properly labelled element is reported as unlabelled.
 */
final class AriaArgumentTest extends TestCase
{
    public function testLinkLabelledByTheAriaArgumentIsAllowed(): void
    {
        // Reported as a false positive in issue #1.
        $source = <<<'FLUID'
            <f:link.action action="show" class="card"
                           aria="{label: 'Link to device {Title}'}"
                           arguments="{uid: item.uid}">
              <f:render section="card-content" arguments="{_all}" />
            </f:link.action>
            FLUID;

        self::assertSame([], $this->runRule(new LinkNameRule(), $source));
    }

    public function testLinkWhoseOnlyContentIsARenderedSectionIsAllowed(): void
    {
        // What the section renders cannot be known statically, so assume text.
        self::assertSame([], $this->runRule(new LinkNameRule(), '<a href="/x"><f:render section="body"/></a>'));
    }

    public function testIconOnlyLinkIsStillReported(): void
    {
        self::assertCount(1, $this->runRule(new LinkNameRule(), '<a href="/x"><svg><path/></svg></a>'));
    }

    public function testButtonLabelledByTheAriaArgumentIsAllowed(): void
    {
        $source = "<f:form.button aria=\"{label: 'Close'}\"><svg><path/></svg></f:form.button>";

        self::assertSame([], $this->runRule(new ButtonNameRule(), $source));
    }

    public function testFormControlLabelledByTheAriaArgumentIsAllowed(): void
    {
        $source = "<f:form.textfield name=\"q\" aria=\"{label:'Search'}\"/>";

        self::assertSame([], $this->runRule(new FormLabelRule(), $source));
    }

    public function testFormControlLabelledViaAdditionalAttributesIsAllowed(): void
    {
        $source = "<f:form.textfield name=\"q\" additionalAttributes=\"{'aria-label': 'Search'}\"/>";

        self::assertSame([], $this->runRule(new FormLabelRule(), $source));
    }

    public function testAnUnreadableAriaArgumentSilencesTheNameRules(): void
    {
        self::assertSame([], $this->runRule(new LinkNameRule(), '<a href="/x" aria="{attrs}"><svg/></a>'));
    }

    public function testAriaStatesFromTheArgumentAreSeenByOtherRules(): void
    {
        $hidden = '<a href="/x" aria="{hidden: \'true\'}">Away</a>';
        self::assertCount(1, $this->runRule(new AriaHiddenFocusableRule(), $hidden));

        $tab = '<div role="tab" aria="{selected: \'true\'}">One</div>';
        self::assertSame([], $this->runRule(new TabSelectedRule(), $tab));
    }

    public function testATypoInTheAriaArgumentIsReported(): void
    {
        $findings = $this->runRule(new AriaAttributeRule(), "<a href=\"/x\" aria=\"{lable: 'Home'}\">x</a>");

        self::assertCount(1, $findings);
        self::assertStringContainsString('aria-lable', $findings[0]->message);
    }

    public function testAnUnreadableAriaArgumentIsNotReportedAsATypo(): void
    {
        self::assertSame([], $this->runRule(new AriaAttributeRule(), '<a href="/x" aria="{attrs}">x</a>'));
    }

    /**
     * @return list<Finding>
     */
    private function runRule(Rule $rule, string $source): array
    {
        $tree = (new TemplateParser())->parse($source);

        return $rule->check(new ParsedTemplate('template.html', $tree));
    }
}
