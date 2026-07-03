<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Template;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Template\TemplateFinder;

final class TemplateFinderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/fluid-lens-finder-' . uniqid('', true);
        mkdir($this->root . '/Tests', 0777, true);
        file_put_contents($this->root . '/Real.html', '<div/>');
        file_put_contents($this->root . '/Tests/Fixture.html', '<div/>');
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/Tests/Fixture.html');
        unlink($this->root . '/Real.html');
        rmdir($this->root . '/Tests');
        rmdir($this->root);
    }

    public function testFindsAllTemplatesWithoutExcludes(): void
    {
        self::assertCount(2, (new TemplateFinder())->find($this->root));
    }

    public function testGlobExcludeSkipsMatchingFiles(): void
    {
        $files = (new TemplateFinder())->find($this->root, ['*/Tests/*']);

        self::assertCount(1, $files);
        self::assertStringEndsWith('Real.html', $files[0]);
    }
}
