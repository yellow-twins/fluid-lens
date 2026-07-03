<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Template;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Template\SuppressionScanner;

final class SuppressionScannerTest extends TestCase
{
    public function testMarkerIgnoresItsOwnLineAndTheNext(): void
    {
        $source = "line one\n{# @fluidlint-ignore keep inline #}\n<div>x</div>\nline four";

        $ignored = (new SuppressionScanner())->scan($source);

        self::assertArrayHasKey(2, $ignored);
        self::assertArrayHasKey(3, $ignored);
        self::assertArrayNotHasKey(1, $ignored);
        self::assertArrayNotHasKey(4, $ignored);
    }

    public function testSourceWithoutMarkerIgnoresNothing(): void
    {
        self::assertSame([], (new SuppressionScanner())->scan("<div>\n<span>x</span>\n</div>"));
    }
}
