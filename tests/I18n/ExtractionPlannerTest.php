<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\I18n;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\I18n\ExtractionPlan;
use YellowTwins\FluidLens\I18n\ExtractionPlanner;
use YellowTwins\FluidLens\I18n\ExtractionSettings;
use YellowTwins\FluidLens\I18n\ExtractionWriter;
use YellowTwins\FluidLens\I18n\HardcodedTextFinder;
use YellowTwins\FluidLens\I18n\TargetResolver;
use YellowTwins\FluidLens\I18n\TranslateStyle;

final class ExtractionPlannerTest extends TestCase
{
    private const TEMPLATE = 'ext/Resources/Private/Templates/Event/List.html';

    private const LOCALLANG = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
            <file source-language="en" datatype="plaintext" original="EXT:ext/Resources/Private/Language/locallang.xlf">
                <body>
                    <trans-unit id="common.close">
                        <source>Schließen</source>
                    </trans-unit>
                </body>
            </file>
        </xliff>

        XML;

    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = new TemporaryProject();
        $this->project->addExtension('ext', 'ext');
        $this->project->write('ext/Resources/Private/Language/locallang.xlf', self::LOCALLANG);
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testRewritesTheTemplateAndExtendsTheLanguageFiles(): void
    {
        $this->project->write(self::TEMPLATE, <<<'HTML'
            <h2>Kommende Events</h2>
            <p>Hallo {user.name}!</p>
            <button title="Schließen">x</button>
            <p>Bitte <a href="#">hier</a> klicken</p>

            HTML);

        $plan = $this->plan(new ExtractionSettings(TranslateStyle::Tag, 'de', 'en'));
        (new ExtractionWriter())->write($plan->changes);

        self::assertSame(<<<'HTML'
            <h2><f:translate key="event.list.kommendeEvents" extensionName="ext" /></h2>
            <p><f:translate key="event.list.hallo" extensionName="ext" arguments="{0: user.name}" /></p>
            <button title="{f:translate(key: 'common.close', extensionName: 'ext')}">x</button>
            <p>Bitte <a href="#">hier</a> klicken</p>

            HTML, $this->project->read(self::TEMPLATE));

        $locallang = $this->project->read('ext/Resources/Private/Language/locallang.xlf');
        self::assertStringContainsString('<trans-unit id="event.list.hallo">', $locallang);
        self::assertStringContainsString('<source>Hallo %1$s!</source>', $locallang);
        self::assertSame(1, substr_count($locallang, 'common.close'), 'an existing label is reused, not repeated');

        $german = $this->project->read('ext/Resources/Private/Language/de.locallang.xlf');
        self::assertStringContainsString('target-language="de"', $german);
        self::assertStringContainsString('<target>Kommende Events</target>', $german);
        self::assertStringContainsString('<trans-unit id="common.close">', $german);

        self::assertCount(1, $plan->manual());
        self::assertSame([], $this->plan(new ExtractionSettings())->extractable(), 'a second run finds nothing new');
    }

    public function testFollowsTheTranslateStyleTheTemplatesUse(): void
    {
        $this->project->write(self::TEMPLATE, "{f:translate(key: 'a')}{f:translate(key: 'b')}<p>Weiter</p>");

        $plan = $this->plan(new ExtractionSettings());

        self::assertStringContainsString(
            "<p>{f:translate(key: 'event.list.weiter', extensionName: 'ext')}</p>",
            $plan->changes[0]->after,
        );
    }

    public function testTemplatesWithoutALanguageFileAreNotExtracted(): void
    {
        $this->project->write('loose/Page.html', '<p>Hello world</p>');
        $file = $this->project->path('loose/Page.html');
        $source = (string) file_get_contents($file);
        $finder = new HardcodedTextFinder();

        $planner = new ExtractionPlanner(new TargetResolver(), $finder);
        $plan = $planner->plan($finder->find($file, $source), [$file => $source]);

        self::assertSame([], $plan->changes);
        self::assertStringContainsString('no language file', (string) $plan->manual()[0]->blocker());
    }

    private function plan(ExtractionSettings $settings): ExtractionPlan
    {
        $file = $this->project->path(self::TEMPLATE);
        $source = (string) file_get_contents($file);
        $finder = new HardcodedTextFinder();
        $planner = new ExtractionPlanner(new TargetResolver(), $finder, $settings);

        return $planner->plan($finder->find($file, $source), [$file => $source]);
    }
}
