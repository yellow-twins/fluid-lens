<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Support;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Support\FluidArray;

final class FluidArrayTest extends TestCase
{
    public function testParsesASingleKeyValuePair(): void
    {
        self::assertSame(['label' => 'Save'], FluidArray::parse("{label: 'Save'}"));
    }

    public function testToleratesMissingWhitespaceAndSeveralPairs(): void
    {
        self::assertSame(
            ['label' => 'Search', 'hidden' => 'true'],
            FluidArray::parse("{label:'Search',hidden:'true'}"),
        );
    }

    public function testKeepsAnEmbeddedExpressionVerbatim(): void
    {
        self::assertSame(
            ['label' => 'Link to device {Title}'],
            FluidArray::parse("{label: 'Link to device {Title}'}"),
        );
    }

    public function testAcceptsQuotedKeysAndUnquotedValues(): void
    {
        self::assertSame(['aria-label' => '{item.title}'], FluidArray::parse("{'aria-label': {item.title}}"));
    }

    public function testKeepsANestedArrayAsOneRawValue(): void
    {
        self::assertSame(['data' => "{foo: 'bar'}"], FluidArray::parse("{data: {foo: 'bar'}}"));
    }

    public function testEmptyArrayHasNoPairs(): void
    {
        self::assertSame([], FluidArray::parse('{}'));
    }

    public function testAPlainVariableIsNotAnArray(): void
    {
        self::assertNull(FluidArray::parse('{ariaAttributes}'));
        self::assertNull(FluidArray::parse('{item.title}'));
        self::assertNull(FluidArray::parse('{_all}'));
    }

    public function testAnInlineViewHelperIsNotAnArray(): void
    {
        self::assertNull(FluidArray::parse("{f:translate(key: 'label.save')}"));
    }

    public function testValuesWithoutBracesAreNotAnArray(): void
    {
        self::assertNull(FluidArray::parse('movie.swf'));
        self::assertNull(FluidArray::parse(''));
    }

    public function testTwoSeparateExpressionsAreNotOneArray(): void
    {
        self::assertNull(FluidArray::parse('{first} {second}'));
    }

    public function testACommaInsideQuotesDoesNotSplitPairs(): void
    {
        self::assertSame(['label' => 'Save, then close'], FluidArray::parse("{label: 'Save, then close'}"));
    }
}
