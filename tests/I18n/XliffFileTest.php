<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\I18n;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\I18n\TranslationUnit;
use YellowTwins\FluidLens\I18n\XliffException;
use YellowTwins\FluidLens\I18n\XliffFile;

final class XliffFileTest extends TestCase
{
    private const HEADER = ['sourceLanguage' => 'en', 'targetLanguage' => null, 'original' => 'x', 'product' => 'x'];

    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = new TemporaryProject();
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testAppendsUnitsInTheFilesOwnIndentation(): void
    {
        $existing = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
              <file source-language="en" datatype="plaintext" original="x">
                <body>
                  <trans-unit id="a">
                    <source>A</source>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XML;
        $file = XliffFile::open($this->project->write('locallang.xlf', $existing));

        self::assertSame(['a' => 'A'], $file->sources);
        $after = $file->withUnits([new TranslationUnit('b', 'Fish & <Chips>')], self::HEADER);

        self::assertStringContainsString(
            "      </trans-unit>\n      <trans-unit id=\"b\">\n        <source>Fish &amp; &lt;Chips&gt;</source>\n"
            . "      </trans-unit>\n    </body>",
            $after,
        );
        $untouched = (int) strpos($existing, '    </body>');
        self::assertSame(substr($existing, 0, $untouched), substr($after, 0, $untouched));
    }

    public function testCreatesAValidFileWithTargets(): void
    {
        $file = XliffFile::open($this->project->path('de.locallang.xlf'));
        self::assertFalse($file->exists());

        $content = $file->withUnits(
            [new TranslationUnit('k', 'Größe', 'Größe')],
            ['sourceLanguage' => 'en', 'targetLanguage' => 'de', 'original' => 'EXT:x/l.xlf', 'product' => 'x'],
        );

        $document = new DOMDocument();
        self::assertTrue($document->loadXML($content));
        self::assertSame('de', $document->getElementsByTagName('file')->item(0)?->getAttribute('target-language'));
        self::assertSame('Größe', $document->getElementsByTagName('target')->item(0)?->textContent);
    }

    public function testRejectsUnreadableFiles(): void
    {
        $this->expectException(XliffException::class);

        XliffFile::open($this->project->write('broken.xlf', '<xliff><file>'));
    }
}
