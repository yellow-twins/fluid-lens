<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fluid;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fluid\SourceCasing;

final class SourceCasingTest extends TestCase
{
    public function testRestoresTagAndAttributeSpelling(): void
    {
        $casing = SourceCasing::fromSource('<st:backHeadline title="x" /><f:translate extensionName="p" />');

        self::assertSame('st:backHeadline', $casing->restoreTag('st:backheadline'));
        self::assertSame('extensionName', $casing->restoreAttribute('extensionname'));
    }

    public function testReturnsUnknownNamesUnchanged(): void
    {
        $casing = SourceCasing::fromSource('<div></div>');

        self::assertSame('span', $casing->restoreTag('span'));
        self::assertSame('data-x', $casing->restoreAttribute('data-x'));
    }
}
