<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Baseline\ComponentBaseline;
use YellowTwins\FluidLens\Component\Model\CandidateOccurrence;
use YellowTwins\FluidLens\Component\Model\CandidateScore;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;

final class ComponentBaselineTest extends TestCase
{
    private function candidate(string $id, int $callSites): ComponentCandidate
    {
        $occurrences = [];
        for ($i = 1; $i <= $callSites; $i++) {
            $occurrences[] = new CandidateOccurrence('a.html', $i);
        }

        $score = new CandidateScore(1.0, []);

        return new ComponentCandidate($id, 'Card', 'st:card', [], 3, $occurrences, [], null, [], [], $score);
    }

    public function testSuppressesKnownSuggestionsUntilTheyGrow(): void
    {
        $baseline = ComponentBaseline::fromCandidates([$this->candidate('partial:Card.html', 2)]);

        self::assertSame([], $baseline->filter([$this->candidate('partial:Card.html', 2)]));
        self::assertCount(1, $baseline->filter([$this->candidate('partial:Card.html', 3)]));
        self::assertCount(1, $baseline->filter([$this->candidate('clone:abc', 2)]));
    }

    public function testRoundTripsThroughJson(): void
    {
        $baseline = ComponentBaseline::fromCandidates([
            $this->candidate('partial:B.html', 2),
            $this->candidate('clone:a', 4),
        ]);

        $restored = ComponentBaseline::fromJson($baseline->toJson());

        self::assertSame(2, $restored->count());
        self::assertSame($baseline->toJson(), $restored->toJson());
        self::assertStringContainsString('"clone:a": 4', $baseline->toJson());
    }
}
