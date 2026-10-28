<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\I18n;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\I18n\FinderSettings;
use YellowTwins\FluidLens\I18n\HardcodedText;
use YellowTwins\FluidLens\I18n\HardcodedTextFinder;
use YellowTwins\FluidLens\I18n\TextKind;

final class HardcodedTextFinderTest extends TestCase
{
    public function testFindsTextContentWithItsExactRange(): void
    {
        $source = "<p>\n    No events found.\n</p>";
        $texts = $this->find($source);

        self::assertCount(1, $texts);
        self::assertSame('No events found.', substr($source, $texts[0]->start, $texts[0]->end - $texts[0]->start));
        self::assertSame(2, $texts[0]->line);
        self::assertSame('p', $texts[0]->context);
        self::assertSame('No events found.', $texts[0]->message?->label);
    }

    public function testIgnoresWhatIsNoProse(): void
    {
        $source = <<<'HTML'
            <p>{event.title}</p>
            <p>12,50 € – 3</p>
            <p>&nbsp;|&nbsp;</p>
            <span>ST-2026</span>
            <f:variable name="id">{prefix}Address</f:variable>
            <f:variable name="state">active</f:variable>
            <code>console.log</code>
            <script>var label = "Hello";</script>
            <f:comment>Explains things</f:comment>
            <!-- Some comment -->
            <f:translate key="x">Fallback text</f:translate>
            <a title="{f:translate(key: 'x')}" href="#">{label}</a>
            HTML;

        self::assertSame([], $this->find($source));
    }

    public function testTurnsPlainVariablesIntoPlaceholders(): void
    {
        $texts = $this->find('<p>Hello {user.name}, you have {count} new messages (100%).</p>');

        self::assertSame('Hello %1$s, you have %2$s new messages (100%%).', $texts[0]->message?->label);
        self::assertSame(['user.name', 'count'], $texts[0]->message->arguments);
    }

    public function testOtherExpressionsNeedAHuman(): void
    {
        $texts = $this->find('<p>From {price -> f:format.currency()} per night</p>');

        self::assertFalse($texts[0]->isExtractable());
        self::assertStringContainsString('not a plain variable', (string) $texts[0]->reason);
    }

    public function testASentenceWithInlineMarkupIsOneManualFinding(): void
    {
        $texts = $this->find('<p>Please <a href="#">click here</a> to continue.</p>');

        self::assertCount(1, $texts);
        self::assertFalse($texts[0]->isExtractable());
        self::assertSame('Please click here to continue.', $texts[0]->text);
    }

    public function testTextBetweenSiblingsJoinsThemIntoOneSentence(): void
    {
        $texts = $this->find(
            '<div><span>Please agree in</span> <strong>knowledge of the risks</strong> to it.</div>',
        );

        self::assertCount(1, $texts);
        self::assertFalse($texts[0]->isExtractable());
        self::assertSame('Please agree in knowledge of the risks to it.', $texts[0]->text);
    }

    public function testPartlyTranslatedTextNamesTheTranslation(): void
    {
        $texts = $this->find('<f:image src="x.svg" alt="{f:translate(key: \'close\')} icon" />');

        self::assertStringContainsString('partly translated already', (string) $texts[0]->reason);
    }

    public function testIgnoresAssetCodeAndLongTokens(): void
    {
        $source = '<f:asset.script identifier="x">var label = "Hello there";</f:asset.script>'
            . '<template>data:application/zip;base64,UEsDBBQAAAgIAMhjLF1Uv+WCJhAAACj9AAAZAAAAYTEw</template>';

        self::assertSame([], $this->find($source));
    }

    public function testSiblingElementsStandAlone(): void
    {
        $texts = $this->find('<dl><span>Date</span><span>Venue</span></dl><a><span>Read</span> <svg></svg></a>');

        self::assertSame(
            ['Date', 'Venue', 'Read'],
            array_map(static fn (HardcodedText $t): string => $t->text, $texts),
        );
        self::assertTrue($texts[0]->isExtractable());
    }

    public function testFindsTranslatableAttributesAndArguments(): void
    {
        $source = <<<'HTML'
            <img src="a.png" alt="Our team">
            <input type="submit" value="Send now">
            <input type="hidden" value="secret value">
            <f:form.submit value="Save" />
            <f:link.page pageUid="1" title="More about {artist.name}">x</f:link.page>
            <div class="Not translatable" data-x="Not either"></div>
            HTML;
        $texts = $this->find($source);

        self::assertSame(
            ['img alt', 'input value', 'f:form.submit value', 'f:link.page title'],
            array_map(static fn (HardcodedText $t): string => $t->context, $texts),
        );
        self::assertSame(TextKind::Attribute, $texts[0]->kind);
        self::assertSame('"', $texts[0]->quote);
        self::assertSame('More about %1$s', $texts[3]->message?->label);
    }

    public function testLiteralsInArrayArgumentsAreReportedForAHuman(): void
    {
        $texts = $this->find(
            '<f:form.textfield aria="{label: \'Search events\'}" />'
            . '<f:form.textfield aria="{label: \'{f:translate(key: \\\'x\\\')}\'}" />',
        );

        self::assertCount(1, $texts);
        self::assertSame('f:form.textfield aria.label', $texts[0]->context);
        self::assertFalse($texts[0]->isExtractable());
    }

    public function testHonoursIgnoreMarkersIgnoreListAndExtraAttributes(): void
    {
        $finder = new HardcodedTextFinder(new FinderSettings(2, ['Sachsentrance', '/^Instagram$/'], ['data-title']));
        $source = <<<'HTML'
            <h1>Sachsentrance</h1>
            <span>Instagram</span>
            <!-- @fluidlint-ignore -->
            <p>Keep me</p>
            <button data-title="Really cancel?">x</button>
            HTML;

        $texts = $finder->find('a.html', $source);

        self::assertSame(['button data-title'], array_map(static fn (HardcodedText $t): string => $t->context, $texts));
    }

    /**
     * @return list<HardcodedText>
     */
    private function find(string $source): array
    {
        return (new HardcodedTextFinder())->find('Template.html', $source);
    }
}
