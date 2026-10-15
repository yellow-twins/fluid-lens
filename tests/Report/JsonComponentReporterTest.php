<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Report;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\CandidateOccurrence;
use YellowTwins\FluidLens\Component\Model\CandidateScore;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\ComponentDraft;
use YellowTwins\FluidLens\Component\Model\ComponentReport;
use YellowTwins\FluidLens\Component\Model\ExclusionReason;
use YellowTwins\FluidLens\Component\Model\KeptPartial;
use YellowTwins\FluidLens\Report\JsonComponentReporter;

final class JsonComponentReporterTest extends TestCase
{
    public function testSerialisesEverythingNeededToActOnASuggestion(): void
    {
        $candidate = new ComponentCandidate(
            id: 'partial:Card',
            name: 'Card',
            tag: 'st:card',
            sources: [CandidateSource::Partial],
            elementCount: 4,
            occurrences: [new CandidateOccurrence('a.html', 3, '<st:card item="{item}" />')],
            arguments: [new ArgumentProposal('item', ArgumentType::Object, false, null, ArgumentOrigin::Variable)],
            slot: null,
            reasons: ['rendered twice'],
            caveats: [new CandidateNote(CandidateNote::IMPLICIT_SETTINGS, 'reads settings')],
            score: new CandidateScore(9.5, ['call sites' => 2.0]),
            draft: new ComponentDraft('Components/Card/Card.fluid.html', '<html></html>'),
        );
        $report = new ComponentReport(
            12,
            [$candidate],
            [new KeptPartial('Form/Field', 'f.html', 4, [ExclusionReason::FormContext])],
        );

        /** @var array<string, mixed> $json */
        $json = json_decode((new JsonComponentReporter())->render($report), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(12, $json['filesScanned']);
        self::assertSame(1, $json['candidateCount']);
        self::assertSame(
            [
                'id' => 'partial:Card',
                'name' => 'Card',
                'tag' => 'st:card',
                'score' => 9.5,
                'priority' => 'medium',
                'scoreFactors' => ['call sites' => 2],
                'sources' => ['partial'],
                'elementCount' => 4,
                'callSites' => 1,
                'files' => 1,
                'reasons' => ['rendered twice'],
                'caveats' => [['code' => 'implicit-settings', 'message' => 'reads settings']],
                'arguments' => [[
                    'name' => 'item',
                    'type' => 'object',
                    'optional' => false,
                    'default' => null,
                    'origin' => 'variable',
                    'observedValues' => [],
                ]],
                'slot' => null,
                'draft' => ['path' => 'Components/Card/Card.fluid.html', 'source' => '<html></html>'],
                'occurrences' => [['file' => 'a.html', 'line' => 3, 'call' => '<st:card item="{item}" />']],
                'partial' => null,
                'existingMatch' => null,
            ],
            $json['candidates'][0] ?? null,
        );
        self::assertSame('form-context', $json['keptAsPartials'][0]['reasons'][0]['code'] ?? null);
    }
}
