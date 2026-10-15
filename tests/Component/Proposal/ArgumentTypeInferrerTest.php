<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Proposal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Component\Proposal\ArgumentTypeInferrer;
use YellowTwins\FluidLens\Fluid\VariableUsage;

final class ArgumentTypeInferrerTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, ArgumentType}>
     */
    public static function values(): iterable
    {
        yield 'integers' => [['3', '12'], ArgumentType::Int];
        yield 'zero and one' => [['0', '1'], ArgumentType::Bool];
        yield 'floats' => [['1.5', '2'], ArgumentType::Float];
        yield 'boolean expressions' => [['{true}', '{false}'], ArgumentType::Bool];
        yield 'array literal' => [["{0: 'a'}"], ArgumentType::Array];
        yield 'text' => [['More', 'Less'], ArgumentType::String];
        yield 'variables' => [['{item}', '{other}'], ArgumentType::Mixed];
        yield 'text and a variable' => [['More', '{label}'], ArgumentType::String];
        yield 'nothing seen' => [[], ArgumentType::Mixed];
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('values')]
    public function testInfersFromValues(array $values, ArgumentType $expected): void
    {
        self::assertSame($expected, (new ArgumentTypeInferrer())->infer($values));
    }

    public function testUsageWinsOverValues(): void
    {
        $inferrer = new ArgumentTypeInferrer();

        self::assertSame(
            ArgumentType::Array,
            $inferrer->infer(['{x}'], new VariableUsage('items', ['items'], [VariableUsage::HINT_EACH])),
        );
        self::assertSame(
            ArgumentType::Bool,
            $inferrer->infer([], new VariableUsage('open', ['open'], [VariableUsage::HINT_CONDITION])),
        );
        self::assertSame(
            ArgumentType::Object,
            $inferrer->infer([], new VariableUsage('item', ['item.title'], [VariableUsage::HINT_OUTPUT])),
        );
    }
}
