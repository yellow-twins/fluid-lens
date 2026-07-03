<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Baseline\Baseline;
use YellowTwins\FluidLens\Detector\CloneGroup;
use YellowTwins\FluidLens\Detector\CloneOccurrence;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

final class BaselineTest extends TestCase
{
    public function testSuppressesKnownGroupButSurfacesAdditionalOccurrence(): void
    {
        $known = $this->group('abc', 2);
        $baseline = Baseline::fromGroups([$known]);

        self::assertSame([], $baseline->filter([$this->group('abc', 2)]));
        self::assertCount(1, $baseline->filter([$this->group('abc', 3)]));
        self::assertCount(1, $baseline->filter([$this->group('xyz', 2)]));
    }

    public function testRoundTripsThroughJson(): void
    {
        $baseline = Baseline::fromGroups([$this->group('abc', 4)]);

        $restored = Baseline::fromJson($baseline->toJson());

        self::assertSame([], $restored->filter([$this->group('abc', 4)]));
    }

    private function group(string $hash, int $occurrences): CloneGroup
    {
        $node = new Node(NodeType::Element, 'div');
        $list = [];
        for ($i = 0; $i < $occurrences; $i++) {
            $list[] = new CloneOccurrence('file.html', $i + 1, $node);
        }

        /** @var non-empty-list<CloneOccurrence> $list */
        return new CloneGroup($hash, 3, $list);
    }
}
