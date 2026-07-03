<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Config;

/**
 * Loads a `fluid-lens.php` configuration file, which returns a nested array.
 *
 * When no path is given the file is auto-discovered in the working directory;
 * if it is absent, an empty configuration (all defaults) is returned. An
 * explicitly requested file that is missing or malformed is an error.
 */
final class ConfigLoader
{
    public const DEFAULT_FILE = 'fluid-lens.php';

    public function load(?string $path): Config
    {
        $file = $this->resolveFile($path);
        if ($file === null) {
            return Config::empty();
        }

        /** @psalm-suppress UnresolvableInclude */
        $data = require $file;
        if (!is_array($data)) {
            throw new ConfigException(sprintf('Config file "%s" must return an array.', $file));
        }

        return $this->fromArray($data);
    }

    private function resolveFile(?string $path): ?string
    {
        if ($path !== null) {
            if (!is_file($path)) {
                throw new ConfigException(sprintf('Config file not found: %s', $path));
            }

            return $path;
        }

        return is_file(self::DEFAULT_FILE) ? self::DEFAULT_FILE : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function fromArray(array $data): Config
    {
        $lint = $this->section($data, 'lint');
        $analyze = $this->section($data, 'analyze');
        $similar = $this->section($data, 'similar');

        return new Config(
            paths: $this->stringList($data['paths'] ?? null),
            excludePaths: $this->stringList($data['exclude'] ?? null),
            lintOnly: $this->stringList($lint['only'] ?? null),
            lintExclude: $this->stringList($lint['exclude'] ?? null),
            lintFailOn: $this->stringOrNull($lint['failOn'] ?? null),
            lintBaseline: $this->stringOrNull($lint['baseline'] ?? null),
            cloneMinElements: $this->intOrNull($analyze['minElements'] ?? null),
            cloneMinOccurrences: $this->intOrNull($analyze['minOccurrences'] ?? null),
            baseline: $this->stringOrNull($analyze['baseline'] ?? null),
            similarThreshold: $this->floatOrNull($similar['threshold'] ?? null),
            similarMinElements: $this->intOrNull($similar['minElements'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function section(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
