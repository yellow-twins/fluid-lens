<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\Linter;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class LinterTest extends TestCase
{
    public function testDefaultLinterAggregatesFindingsSortedByLocation(): void
    {
        $source = "<html>\n  <img src=\"a.jpg\"/>\n  <a href=\"/x\"><svg><path/></svg></a>\n</html>";
        $template = new ParsedTemplate('page.html', (new TemplateParser())->parse($source));

        $findings = Linter::withDefaultRules()->lint([$template]);

        self::assertNotEmpty($findings);

        $lines = array_map(static fn ($finding): int => $finding->line, $findings);
        $sorted = $lines;
        sort($sorted);
        self::assertSame($sorted, $lines);
    }

    public function testCleanTemplateProducesNoFindings(): void
    {
        $source = '<div class="wrap"><a href="/x">Home</a></div>';
        $template = new ParsedTemplate('clean.html', (new TemplateParser())->parse($source));

        self::assertSame([], Linter::withDefaultRules()->lint([$template]));
    }
}
