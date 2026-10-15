<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\CandidateDeduplicator;
use YellowTwins\FluidLens\Component\Model\CandidateScore;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class CandidateDeduplicatorTest extends TestCase
{
    private function candidate(string $name, float $score): ComponentCandidate
    {
        $score = new CandidateScore($score, []);

        return new ComponentCandidate($name, $name, 'st:' . $name, [], 1, [], [], null, [], [], $score);
    }

    public function testDropsACandidateInsideABetterOne(): void
    {
        $tree = (new TemplateParser())->parse('<div><section><p>a</p></section></div>');
        $outer = $tree->elementChildren()[0];
        $inner = $outer->elementChildren()[0];

        $deduplicator = new CandidateDeduplicator();
        $deduplicator->add($this->candidate('Inner', 5.0), [$inner]);
        $deduplicator->add($this->candidate('Outer', 9.0), [$outer]);

        [$kept, $dropped] = $deduplicator->result();

        self::assertSame(['Outer'], array_map(static fn (ComponentCandidate $c): string => $c->name, $kept));
        self::assertSame(1, $dropped);
    }

    public function testKeepsABetterCandidateInsideAWeakerOne(): void
    {
        $tree = (new TemplateParser())->parse('<div><section><p>a</p></section></div>');
        $outer = $tree->elementChildren()[0];
        $inner = $outer->elementChildren()[0];

        $deduplicator = new CandidateDeduplicator();
        $deduplicator->add($this->candidate('Inner', 9.0), [$inner]);
        $deduplicator->add($this->candidate('Outer', 5.0), [$outer]);

        self::assertCount(2, $deduplicator->result()[0]);
    }
}
