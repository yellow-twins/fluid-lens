<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fluid;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fluid\Expressions;

final class ExpressionsTest extends TestCase
{
    public function testFindsTopLevelExpressions(): void
    {
        self::assertSame(
            ['a.b', "f:translate(key: 'x', arguments: {0: c})"],
            Expressions::in("It's {a.b} and {f:translate(key: 'x', arguments: {0: c})}"),
        );
    }

    public function testIgnoresBracesInsideQuotes(): void
    {
        self::assertSame(["x -> f:format.raw(default: '}')"], Expressions::in("{x -> f:format.raw(default: '}')}"));
    }

    public function testRecognisesASingleExpression(): void
    {
        self::assertTrue(Expressions::isSingle(' {item.title} '));
        self::assertFalse(Expressions::isSingle('Hello {name}'));
        self::assertFalse(Expressions::isSingle('{a}{b}'));
    }
}
