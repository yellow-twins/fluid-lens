<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Baseline\LintBaseline;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Severity;

final class LintBaselineTest extends TestCase
{
    public function testSuppressesKnownFindingsRegardlessOfLine(): void
    {
        $baseline = LintBaseline::fromFindings([$this->finding('a.html', 10)]);

        // Same rule/file/message on a different line is still covered.
        self::assertSame([], $baseline->filter([$this->finding('a.html', 42)]));
    }

    public function testSurfacesNewFindings(): void
    {
        $baseline = LintBaseline::fromFindings([$this->finding('a.html', 1)]);

        // A second occurrence of the same finding is new.
        self::assertCount(1, $baseline->filter([$this->finding('a.html', 1), $this->finding('a.html', 2)]));
        // A finding in another file is new.
        self::assertCount(1, $baseline->filter([$this->finding('b.html', 1)]));
    }

    public function testRoundTripsThroughJson(): void
    {
        $baseline = LintBaseline::fromFindings([$this->finding('a.html', 1)]);

        self::assertSame([], LintBaseline::fromJson($baseline->toJson())->filter([$this->finding('a.html', 9)]));
    }

    private function finding(string $file, int $line): Finding
    {
        return new Finding('wcag.img-alt', Severity::Error, 'No alt', $file, $line, 'WCAG 1.1.1 (A)');
    }
}
