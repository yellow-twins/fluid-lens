<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\AdvisorSettings;
use YellowTwins\FluidLens\Component\ComponentAdvisor;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentReport;
use YellowTwins\FluidLens\Component\Model\ExclusionReason;

/**
 * A small project end to end: a card Partial rendered from three templates, a
 * form Partial that has to stay one, a Partial that only wraps a component, an
 * existing component and a Partial nobody renders.
 */
final class ComponentAdvisorTest extends TestCase
{
    private const COLLECTION = 'xmlns:site="http://typo3.org/ns/Vendor/Site/Components"';

    private function advise(): ComponentReport
    {
        $root = 'site/Resources/Private/';

        return (new ComponentAdvisor(new AdvisorSettings(sources: [CandidateSource::Partial])))->advise(
            TemplateFactory::many([
                $root . 'Templates/News/List.html' => '<html ' . self::COLLECTION . '>'
                    . '<f:for each="{news}" as="item"><f:render partial="Card" arguments="{item: item}" /></f:for>'
                    . '<site:icon name="x" /></html>',
                $root . 'Templates/News/Show.html' => '<f:render partial="Card" arguments="{item: related, wide: 1}" />'
                    . '<f:render partial="Form/Field" arguments="{name: \'title\'}" />'
                    . '<f:render partial="IconOnly" />',
                $root . 'Templates/Page/Home.html' => '<f:render partial="Card" arguments="{_all}" />'
                    . '<f:form action="send"><f:render partial="Form/Field" arguments="{name: \'mail\'}" /></f:form>'
                    . '<f:render partial="IconOnly" />',
                $root . 'Partials/Card.html' => '<article class="card{f:if(condition: wide, then: \' card--wide\')}">'
                    . '<h2><f:link.page pageUid="{settings.detailPid}">{item.title}</f:link.page></h2></article>',
                $root . 'Partials/Form/Field.html' => '<label>{name}<f:form.textfield property="{name}" /></label>',
                $root . 'Partials/IconOnly.html' => '{namespace site=Vendor\\Site\\Components}<site:icon name="y" />',
                $root . 'Partials/Unused.html' => '<p>never rendered</p>',
                $root . 'Components/Icon/Icon.fluid.html' => '<svg><use href="#{name}" /></svg>',
            ]),
        );
    }

    public function testSuggestsTheRenderedCardPartial(): void
    {
        $report = $this->advise();

        self::assertCount(1, $report->candidates);
        $card = $report->candidates[0];
        self::assertSame('Card', $card->name);
        self::assertSame('site:card', $card->tag);
        self::assertSame(3, $card->callSites());
        self::assertSame(3, $card->fileCount());
    }

    public function testProposesArgumentsFromWhatThePartialReads(): void
    {
        $arguments = [];
        foreach ($this->advise()->candidates[0]->arguments as $argument) {
            $arguments[$argument->name] = $argument;
        }

        self::assertSame(['item', 'detailPid', 'wide'], array_keys($arguments));
        self::assertSame('object', $arguments['item']->typeName());
        self::assertFalse($arguments['item']->optional);
        self::assertSame('bool', $arguments['wide']->typeName());
        self::assertTrue($arguments['wide']->optional);
        self::assertSame('{false}', $arguments['wide']->default);
        self::assertSame(['{settings.detailPid}'], $arguments['detailPid']->observedValues);
    }

    public function testExplainsWhatTheConversionNeeds(): void
    {
        $card = $this->advise()->candidates[0];

        self::assertSame(
            [CandidateNote::IMPLICIT_SETTINGS, CandidateNote::CALLERS_PASS_ALL, CandidateNote::CALLERS_DISAGREE],
            $card->caveatCodes(),
        );
    }

    public function testDraftsTheComponentAndTheCalls(): void
    {
        $card = $this->advise()->candidates[0];

        self::assertNotNull($card->draft);
        self::assertSame('site/Resources/Private/Components/Card/Card.fluid.html', $card->draft->path);
        self::assertStringContainsString('<f:argument name="detailPid" type="mixed" />', $card->draft->source);
        self::assertStringContainsString(
            '<f:link.page pageUid="{detailPid}">{item.title}</f:link.page>',
            $card->draft->source,
        );
        self::assertStringNotContainsString('settings', $card->draft->source);

        self::assertSame(
            [
                '<site:card item="{item}" detailPid="{settings.detailPid}" />',
                '<site:card item="{related}" detailPid="{settings.detailPid}" wide="1" />',
                '<site:card item="{item}" detailPid="{settings.detailPid}" />',
            ],
            array_map(static fn ($occurrence): string => $occurrence->call, $card->occurrences),
        );
    }

    public function testKeepsTheFormPartial(): void
    {
        $report = $this->advise();

        self::assertCount(1, $report->keptPartials);
        self::assertSame('Form/Field', $report->keptPartials[0]->name);
        self::assertSame([ExclusionReason::FormContext], $report->keptPartials[0]->reasons);
    }

    public function testReportsTheRestWithoutSuggestingIt(): void
    {
        $report = $this->advise();

        self::assertSame(['Unused'], array_map(static fn ($p): string => $p->name, $report->unreferencedPartials));
        self::assertSame(1, $report->skippedLowPayoff);
        self::assertSame(['icon'], array_map(static fn ($c): string => $c->name, $report->existingComponents));
        self::assertSame(2, $report->existingComponents[0]->usages);
    }

    public function testRaisingTheThresholdDropsTheCandidate(): void
    {
        $report = (new ComponentAdvisor(new AdvisorSettings(minOccurrences: 4, sources: [CandidateSource::Partial])))
            ->advise(TemplateFactory::many([
                'a/Templates/A.html' => '<f:render partial="Card" arguments="{x: 1}" />',
                'a/Templates/B.html' => '<f:render partial="Card" arguments="{x: 2}" />',
                'a/Partials/Card.html' => '<div><p>{x}</p><p>b</p></div>',
            ]));

        self::assertSame([], $report->candidates);
        self::assertSame(1, $report->skippedLowPayoff);
    }
}
