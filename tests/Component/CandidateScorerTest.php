<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\CandidateScorer;

final class CandidateScorerTest extends TestCase
{
    public function testMoreCallSitesAndFilesScoreHigher(): void
    {
        $scorer = new CandidateScorer();

        $few = $scorer->score(2, 10, 1, 2);
        $many = $scorer->score(6, 10, 4, 2);

        self::assertGreaterThan($few->value, $many->value);
        self::assertSame(2.0, $many->factors['spread']);
    }

    public function testTooManyArgumentsHalveTheScore(): void
    {
        $scorer = new CandidateScorer(maxArguments: 3);

        self::assertTrue($scorer->isTooConfigurable(4));
        self::assertSame(0.5, $scorer->score(2, 10, 2, 4)->factors['arguments']);
    }

    public function testAdjustmentsAreFactors(): void
    {
        $score = (new CandidateScorer())->score(4, 15, 3, 0, ['declares arguments' => 1.3]);

        self::assertSame(1.3, $score->factors['declares arguments']);
        self::assertSame('high', $score->priority());
    }
}
