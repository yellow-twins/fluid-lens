<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Index;

use YellowTwins\FluidLens\Fluid\RenderCall;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * All scanned Partials by the name they are rendered with, and the resolution of
 * render calls to them.
 *
 * TYPO3 resolves Partial names through the configured partial root paths, which
 * a static scan cannot see. The resolver therefore prefers a Partial from the
 * caller's own resource root, falls back to the only Partial of that name, and
 * otherwise reports the call as ambiguous or missing — it never guesses.
 */
final class PartialIndex
{
    private const PARTIALS_DIRECTORY = '/Partials/';

    private const RESOURCE_ROOT = '#^(.*)/(?:Templates|Partials|Layouts)/#';

    /**
     * @param array<string, list<PartialFile>> $byName
     */
    private function __construct(
        private readonly array $byName,
    ) {
    }

    /**
     * @param list<ParsedTemplate> $templates
     */
    public static function build(array $templates): self
    {
        $byName = [];
        foreach ($templates as $template) {
            $path = str_replace('\\', '/', $template->file);
            $position = strrpos($path, self::PARTIALS_DIRECTORY);
            if ($position === false || ComponentIndex::isDefinition($path)) {
                continue;
            }

            $relative = substr($path, $position + strlen(self::PARTIALS_DIRECTORY));
            $name = (string) preg_replace('/(\.fluid)?\.html$/', '', $relative);
            $byName[$name][] = new PartialFile($name, substr($path, 0, $position), $template);
        }
        ksort($byName);

        return new self($byName);
    }

    /**
     * @return list<PartialFile>
     */
    public function partials(): array
    {
        $partials = [];
        foreach ($this->byName as $files) {
            array_push($partials, ...$files);
        }

        return $partials;
    }

    public function resolve(RenderCall $call): PartialResolution
    {
        if ($call->partial === null) {
            return new PartialResolution(ResolutionStatus::Missing);
        }

        if ($call->isDynamic()) {
            return new PartialResolution(ResolutionStatus::Dynamic);
        }

        $candidates = $this->byName[$call->partial] ?? [];
        if ($candidates === []) {
            return new PartialResolution(ResolutionStatus::Missing);
        }

        if (count($candidates) === 1) {
            return new PartialResolution(ResolutionStatus::Resolved, $candidates[0]);
        }

        $callerRoot = self::resourceRoot($call->file);
        foreach ($candidates as $candidate) {
            if ($candidate->root === $callerRoot) {
                return new PartialResolution(ResolutionStatus::Resolved, $candidate);
            }
        }

        return new PartialResolution(ResolutionStatus::Ambiguous);
    }

    private static function resourceRoot(string $file): ?string
    {
        $path = str_replace('\\', '/', $file);

        return preg_match(self::RESOURCE_ROOT, $path, $match) === 1 ? $match[1] : null;
    }
}
