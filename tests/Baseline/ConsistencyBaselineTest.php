<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Baseline\ConsistencyBaseline;
use YellowTwins\FluidLens\Consistency\ConsistencyResult;
use YellowTwins\FluidLens\Consistency\Usage;

final class ConsistencyBaselineTest extends TestCase
{
    public function testRoundTripThroughJson(): void
    {
        $baseline = ConsistencyBaseline::fromResults([$this->mixed('icons', ['Font Awesome', 'Bootstrap Icons'])]);

        $restored = ConsistencyBaseline::fromJson($baseline->toJson());

        self::assertSame(1, $restored->count());
        self::assertSame($baseline->toJson(), $restored->toJson());
    }

    public function testAcceptedMixIsSuppressed(): void
    {
        $current = $this->mixed('icons', ['Font Awesome', 'Bootstrap Icons']);
        $baseline = ConsistencyBaseline::fromResults([$current]);

        self::assertSame([], $baseline->filter([$current]));
    }

    public function testNewVariantInAlreadyMixedCheckSurfaces(): void
    {
        $baseline = ConsistencyBaseline::fromResults([$this->mixed('icons', ['Font Awesome', 'Bootstrap Icons'])]);

        $withThird = $this->mixed('icons', ['Font Awesome', 'Bootstrap Icons', 'Material Icons']);

        self::assertCount(1, $baseline->filter([$withThird]));
    }

    public function testPreviouslyConsistentCheckBecomingMixedSurfaces(): void
    {
        // Baseline captured a single variant (consistent). A second one appears.
        $baseline = ConsistencyBaseline::fromResults([$this->mixed('css', ['Bootstrap'])]);

        $nowMixed = $this->mixed('css', ['Bootstrap', 'Tailwind']);

        self::assertCount(1, $baseline->filter([$nowMixed]));
    }

    public function testConsistentResultIsNeverActionable(): void
    {
        $baseline = ConsistencyBaseline::fromResults([]);

        self::assertSame([], $baseline->filter([$this->mixed('css', ['Bootstrap'])]));
    }

    /**
     * @param list<string> $labels
     */
    private function mixed(string $check, array $labels): ConsistencyResult
    {
        $usages = array_map(static fn (string $label): Usage => new Usage($label, ['a.html']), $labels);

        return new ConsistencyResult($check, ucfirst($check), $usages);
    }
}
