<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Proposal;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Proposal\ArgumentNamer;

final class ArgumentNamerTest extends TestCase
{
    public function testNamesASettingAfterItsLastSegment(): void
    {
        $namer = new ArgumentNamer();

        self::assertSame('listPageUid', $namer->forPath('settings.listPageUid'));
        self::assertSame('merch', $namer->forPath('settings.listPid.merch'));
        self::assertSame('detailMerch', $namer->forPath('settings.detail.merch'));
    }

    public function testNumbersNamesThatAreTaken(): void
    {
        $namer = new ArgumentNamer();

        self::assertSame('title', $namer->claim('title'));
        self::assertSame('title2', $namer->claim('title'));
        self::assertSame('_all2', $namer->claim('_all'));
    }
}
