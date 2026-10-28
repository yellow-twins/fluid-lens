<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YellowTwins\FluidLens\Command\I18nCommand;
use YellowTwins\FluidLens\Tests\I18n\TemporaryProject;

final class I18nCommandTest extends TestCase
{
    private const TEMPLATE = 'ext/Resources/Private/Templates/Page.html';

    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = new TemporaryProject();
        $this->project->addExtension('ext', 'ext');
        $this->project->write(self::TEMPLATE, "<h1>Willkommen</h1>\n");
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function testReportsAndFailsWhileTextIsHardcoded(): void
    {
        $tester = $this->runCommand([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('page.willkommen', $tester->getDisplay());
        self::assertStringContainsString('1 text(s) can be moved', $tester->getDisplay());
    }

    public function testJsonOutput(): void
    {
        $tester = $this->runCommand(['--json' => true]);

        /** @var array{extractableCount: int, texts: list<array{key: string}>} $data */
        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['extractableCount']);
        self::assertSame('page.willkommen', $data['texts'][0]['key']);
    }

    public function testDryRunPrintsADiffAndWritesNothing(): void
    {
        $tester = $this->runCommand(['--fix' => true, '--dry-run' => true]);

        self::assertStringContainsString(
            '+<h1><f:translate key="page.willkommen" extensionName="ext" /></h1>',
            $tester->getDisplay(),
        );
        self::assertStringContainsString('+++ b/', $tester->getDisplay());
        self::assertSame("<h1>Willkommen</h1>\n", $this->project->read(self::TEMPLATE));
    }

    public function testFixWritesAndThenPasses(): void
    {
        $tester = $this->runCommand(['--fix' => true, '--language' => 'de']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists($this->project->path('ext/Resources/Private/Language/locallang.xlf'));
        self::assertFileExists($this->project->path('ext/Resources/Private/Language/de.locallang.xlf'));
        self::assertSame(0, $this->runCommand([])->getStatusCode());
    }

    public function testBaselineAcceptsTheCurrentTexts(): void
    {
        $baseline = $this->project->path('baseline.json');
        $this->runCommand(['--generate-baseline' => $baseline]);

        self::assertSame(0, $this->runCommand(['--baseline' => $baseline])->getStatusCode());
    }

    public function testRejectsAnUnknownStyle(): void
    {
        self::assertSame(1, $this->runCommand(['--style' => 'fancy'])->getStatusCode());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runCommand(array $options): CommandTester
    {
        $tester = new CommandTester(new I18nCommand());
        $tester->execute(['path' => $this->project->path('ext')] + $options);

        return $tester;
    }
}
