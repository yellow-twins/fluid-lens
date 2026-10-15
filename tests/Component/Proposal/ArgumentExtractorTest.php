<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Proposal;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Analysis\OccurrenceAligner;
use YellowTwins\FluidLens\Component\Draft\FluidMarkupWriter;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Proposal\ArgumentExtractor;
use YellowTwins\FluidLens\Component\Proposal\MarkupProposal;
use YellowTwins\FluidLens\Fluid\SourceCasing;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class ArgumentExtractorTest extends TestCase
{
    /**
     * @return array{0: MarkupProposal, 1: string}
     */
    private function extract(string $first, string ...$others): array
    {
        $parser = new TemplateParser();
        $root = static fn (string $copy): Node => $parser->parse($copy)->elementChildren()[0];
        $roots = [$root($first), ...array_values(array_map($root, $others))];
        $points = (new OccurrenceAligner())->align($roots) ?? [];
        $casing = SourceCasing::fromSource($first);
        $proposal = (new ArgumentExtractor())->extract($points, count($roots), $casing);

        return [$proposal, (new FluidMarkupWriter())->write([$roots[0]], $proposal->substitutions, $casing)];
    }

    /**
     * @param list<ArgumentProposal> $arguments
     *
     * @return array<string, string>
     */
    private function types(array $arguments): array
    {
        $types = [];
        foreach ($arguments as $argument) {
            $types[$argument->name] = $argument->typeName() . ($argument->optional ? '?' : '');
        }

        return $types;
    }

    public function testTurnsDifferencesIntoArguments(): void
    {
        [$proposal, $markup] = $this->extract(
            '<div class="box yellow"><h2>Next dates</h2><a href="/events" data-pageUid="3">All</a></div>',
            '<div class="box purple"><h2>Our artists</h2><a href="/artists" data-pageUid="4">All</a></div>',
        );

        self::assertSame(
            ['variant' => 'string', 'title' => 'string', 'href' => 'string', 'dataPageUid' => 'int'],
            $this->types($proposal->arguments),
        );
        self::assertSame(
            "<div class=\"box {variant}\">\n    <h2>{title}</h2>\n"
                . "    <a href=\"{href}\" data-pageUid=\"{dataPageUid}\">All</a>\n</div>",
            $markup,
        );
        self::assertSame(
            ['variant' => 'yellow', 'title' => 'Next dates', 'href' => '/events', 'dataPageUid' => '3'],
            $proposal->callValues[0],
        );
    }

    public function testCollapsesDifferentlyNamedObjectsIntoOneArgument(): void
    {
        [$proposal, $markup] = $this->extract(
            '<div class="card"><h3>{event.title}</h3><img src="{event.image}" alt=""></div>',
            '<div class="card"><h3>{artist.title}</h3><img src="{artist.image}" alt=""></div>',
        );

        self::assertSame(['item' => 'object'], $this->types($proposal->arguments));
        self::assertStringContainsString('<h3>{item.title}</h3>', $markup);
        self::assertSame([['item' => '{event}'], ['item' => '{artist}']], $proposal->callValues);
    }

    public function testAnAttributeMissingInSomeCopiesIsOptional(): void
    {
        [$proposal] = $this->extract('<p><a href="x" title="A">x</a></p>', '<p><a href="x">x</a></p>');

        self::assertSame(['title' => 'string?'], $this->types($proposal->arguments));
        self::assertSame([['title' => 'A'], []], $proposal->callValues);
        self::assertSame(CandidateNote::DEFAULT_UNKNOWN, $proposal->caveats[0]->code ?? null);
    }

    public function testReservedNamesAreNotReused(): void
    {
        $parser = new TemplateParser();
        $roots = [
            $parser->parse('<p><b>A</b>{title}</p>')->elementChildren()[0],
            $parser->parse('<p><b>B</b>{title}</p>')->elementChildren()[0],
        ];
        $points = (new OccurrenceAligner())->align($roots) ?? [];

        $proposal = (new ArgumentExtractor())->extract($points, 2, SourceCasing::none(), ['text']);

        // "text" is taken, so the name gets the role of its element.
        self::assertSame(['bText' => 'string'], $this->types($proposal->arguments));
    }
}
