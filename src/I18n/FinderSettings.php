<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Tuning for {@see HardcodedTextFinder}.
 */
final class FinderSettings
{
    public const DEFAULT_MIN_LETTERS = 2;

    /**
     * @param list<string> $ignore     texts to accept as they are; a value wrapped in
     *                                 slashes is a regular expression
     * @param list<string> $attributes further attribute names to treat as translatable
     */
    public function __construct(
        public readonly int $minLetters = self::DEFAULT_MIN_LETTERS,
        public readonly array $ignore = [],
        public readonly array $attributes = [],
    ) {
    }

    public function ignores(string $text): bool
    {
        foreach ($this->ignore as $pattern) {
            if ($this->isRegex($pattern) ? @preg_match($pattern, $text) === 1 : $pattern === $text) {
                return true;
            }
        }

        return false;
    }

    private function isRegex(string $pattern): bool
    {
        return strlen($pattern) > 2 && $pattern[0] === '/' && strrpos($pattern, '/') > 0;
    }
}
