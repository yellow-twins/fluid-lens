<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Config;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Config\ConfigException;
use YellowTwins\FluidLens\Config\ConfigLoader;

final class ConfigLoaderTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/fluid-lens-' . uniqid('', true) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testLoadsNestedValues(): void
    {
        $this->write(<<<'PHP'
            <?php
            return [
                'paths' => ['packages/'],
                'lint' => ['exclude' => ['style.inline']],
                'analyze' => ['minElements' => 5, 'baseline' => 'bl.json'],
                'similar' => ['threshold' => 0.9],
            ];
            PHP);

        $config = (new ConfigLoader())->load($this->file);

        self::assertSame(['packages/'], $config->paths);
        self::assertSame(['style.inline'], $config->lintExclude);
        self::assertSame(5, $config->cloneMinElements);
        self::assertSame('bl.json', $config->baseline);
        self::assertSame(0.9, $config->similarThreshold);
        self::assertNull($config->cloneMinOccurrences);
    }

    public function testMissingExplicitFileThrows(): void
    {
        $this->expectException(ConfigException::class);

        (new ConfigLoader())->load('/does/not/exist.php');
    }

    public function testNonArrayFileThrows(): void
    {
        $this->write("<?php\nreturn 42;");

        $this->expectException(ConfigException::class);

        (new ConfigLoader())->load($this->file);
    }

    private function write(string $contents): void
    {
        file_put_contents($this->file, $contents);
    }
}
