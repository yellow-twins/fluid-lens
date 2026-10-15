<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Analysis;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Analysis\Assessment;
use YellowTwins\FluidLens\Component\Analysis\ComponentSuitability;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\ExclusionReason;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class ComponentSuitabilityTest extends TestCase
{
    private function assess(string $template, string $file = 'ext/Resources/Private/Partials/A.html'): Assessment
    {
        $tree = (new TemplateParser())->parse($template);

        return (new ComponentSuitability())->assess($tree, $tree, $file);
    }

    public function testFormFieldsWithoutTheirFormBlock(): void
    {
        $assessment = $this->assess('<div><f:form.textfield property="name" /></div>');

        self::assertSame([ExclusionReason::FormContext], $assessment->blockers);
    }

    public function testAWholeFormIsFine(): void
    {
        $form = '<f:form action="x"><f:form.textfield property="name" /></f:form>';

        self::assertFalse($this->assess($form)->isBlocked());
    }

    public function testRenderingTheCallersSectionBlocks(): void
    {
        self::assertSame(
            [ExclusionReason::CallerSection],
            $this->assess('<div><f:render section="Content" /></div>')->blockers,
        );
        self::assertFalse(
            $this->assess('<f:render section="Item" /><f:section name="Item"><p>x</p></f:section>')->isBlocked(),
        );
    }

    public function testLayoutsBlock(): void
    {
        self::assertSame(
            [ExclusionReason::Layout],
            $this->assess('<div></div>', 'ext/Resources/Private/Layouts/Default.html')->blockers,
        );
    }

    public function testSettingsAndForwardedScopeAreCaveats(): void
    {
        $assessment = $this->assess('<a href="{settings.pid}"><f:render partial="X" arguments="{_all}" /></a>');

        self::assertFalse($assessment->isBlocked());
        self::assertSame(
            [CandidateNote::IMPLICIT_SETTINGS, CandidateNote::FORWARDS_ALL],
            array_map(static fn (CandidateNote $note): string => $note->code, $assessment->caveats),
        );
    }
}
