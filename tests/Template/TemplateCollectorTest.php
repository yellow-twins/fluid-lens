<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Template;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Detector\CloneDetector;
use YellowTwins\FluidLens\Template\TemplateCollector;

final class TemplateCollectorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fluid-lens-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testSuppressedBlockIsExcludedFromDetection(): void
    {
        $block = "<div class=\"card\"><div class=\"body\"><span>x</span></div></div>";
        // The same block twice would be a clone; suppressing one occurrence drops
        // the pair below the reporting threshold.
        $this->write('a.html', "<main>\n{# @fluidlint-ignore #}\n$block\n</main>");
        $this->write('b.html', "<main>\n$block\n</main>");

        $collection = (new TemplateCollector())->collect($this->directory);
        $groups = (new CloneDetector(minElements: 3))->detect($collection->templates);

        self::assertSame([], $groups);
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->directory . '/' . $name, $contents);
    }
}
