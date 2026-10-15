<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

/**
 * Restores the original spelling of tag and attribute names.
 *
 * The HTML5 parser lowercases every name, which is harmless for analysis but not
 * for generated Fluid: ViewHelper names and arguments are case-sensitive
 * ({@code extensionName}, {@code st:backHeadline}). The first spelling seen in
 * the source wins; a name that never occurs there is returned unchanged.
 */
final class SourceCasing
{
    private const TAG = '/<\/?([A-Za-z][\w.:-]*)/';

    private const ATTRIBUTE = '/\s([A-Za-z_:][\w.:-]*)\s*=\s*["\']/';

    /**
     * @param array<string, string> $tags       lowercase => original
     * @param array<string, string> $attributes lowercase => original
     */
    private function __construct(
        private readonly array $tags,
        private readonly array $attributes,
    ) {
    }

    public static function fromSource(string $source): self
    {
        return new self(self::spellings(self::TAG, $source), self::spellings(self::ATTRIBUTE, $source));
    }

    public static function none(): self
    {
        return new self([], []);
    }

    public function restoreTag(string $name): string
    {
        return $this->tags[strtolower($name)] ?? $name;
    }

    public function restoreAttribute(string $name): string
    {
        return $this->attributes[strtolower($name)] ?? $name;
    }

    /**
     * @return array<string, string>
     */
    private static function spellings(string $pattern, string $source): array
    {
        $spellings = [];
        if (preg_match_all($pattern, $source, $matches) === false) {
            return $spellings;
        }

        foreach ($matches[1] as $name) {
            $spellings[strtolower($name)] ??= $name;
        }

        return $spellings;
    }
}
