<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use YellowTwins\FluidLens\Config\OptionResolver;

final class OptionResolverTest extends TestCase
{
    public function testCommandLineOverridesConfigAndDefault(): void
    {
        $input = $this->input(['--min-elements' => '7']);

        self::assertSame(7, (new OptionResolver())->int($input, 'min-elements', 3, 2));
    }

    public function testConfigUsedWhenOptionAbsent(): void
    {
        $input = $this->input([]);

        self::assertSame(3, (new OptionResolver())->int($input, 'min-elements', 3, 2));
    }

    public function testDefaultUsedWhenNeitherGiven(): void
    {
        $input = $this->input([]);

        self::assertSame(2, (new OptionResolver())->int($input, 'min-elements', null, 2));
    }

    public function testParseCsvSplitsAndTrims(): void
    {
        self::assertSame(['a', 'b', 'c'], OptionResolver::parseCsv(' a, b ,c '));
        self::assertSame([], OptionResolver::parseCsv(null));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function input(array $parameters): ArrayInput
    {
        $definition = new InputDefinition([
            new InputOption('min-elements', null, InputOption::VALUE_REQUIRED),
        ]);

        return new ArrayInput($parameters, $definition);
    }
}
