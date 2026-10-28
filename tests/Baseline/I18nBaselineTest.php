<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Baseline\I18nBaseline;
use YellowTwins\FluidLens\I18n\HardcodedText;
use YellowTwins\FluidLens\I18n\TextKind;

final class I18nBaselineTest extends TestCase
{
    public function testSuppressesKnownTextsRegardlessOfLine(): void
    {
        $baseline = I18nBaseline::fromTexts([$this->text('a.html', 3)]);

        self::assertSame([], I18nBaseline::fromJson($baseline->toJson())->filter([$this->text('a.html', 30)]));
    }

    public function testSurfacesNewTexts(): void
    {
        $baseline = I18nBaseline::fromTexts([$this->text('a.html', 1)]);

        self::assertCount(1, $baseline->filter([$this->text('a.html', 1), $this->text('a.html', 2)]));
        self::assertCount(1, $baseline->filter([$this->text('b.html', 1)]));
        self::assertSame(1, $baseline->count());
    }

    private function text(string $file, int $line): HardcodedText
    {
        return new HardcodedText($file, $line, TextKind::Text, 'p', 'Hello', null, 'x', 0, 5);
    }
}
