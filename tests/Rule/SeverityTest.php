<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Rule\Severity;

final class SeverityTest extends TestCase
{
    public function testRankOrdersBySeriousness(): void
    {
        self::assertGreaterThan(Severity::Warning->rank(), Severity::Error->rank());
        self::assertGreaterThan(Severity::Notice->rank(), Severity::Warning->rank());
    }

    public function testOnlyNoticesDoNotFailTheBuild(): void
    {
        self::assertTrue(Severity::Error->failsBuild());
        self::assertTrue(Severity::Warning->failsBuild());
        self::assertFalse(Severity::Notice->failsBuild());
    }
}
