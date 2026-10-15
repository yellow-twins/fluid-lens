<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use Generator;

/**
 * Parses a Fluid inline array literal such as {@code {label: 'Save', hidden: 'true'}}
 * into its key/value pairs.
 *
 * Fluid Lens analyses markup, not Fluid, so such a literal reaches a rule as a
 * single opaque attribute value. Only the literal form is understood — anything
 * else, a plain variable like {@code {attributes}} or an inline ViewHelper call,
 * is reported as unreadable so callers can stay conservative instead of guessing.
 */
final class FluidArray
{
    /**
     * Keys are unquoted identifiers, numeric list keys or quoted attribute names
     * such as 'aria-label'.
     */
    private const KEY = '/^(?:[A-Za-z_][\w.:-]*|\d+)$/';

    /**
     * An inline ViewHelper call such as {@code {f:translate(key: 'x')}} opens with
     * a namespaced name; its colon would otherwise read like an array key.
     */
    private const INLINE_VIEWHELPER = '/^\{\s*[a-zA-Z][\w.-]*:[\w.:-]+\s*\(/';

    /**
     * @return array<string, string>|null null when the value is not an array literal
     */
    public static function parse(string $value): ?array
    {
        $pairs = self::parseRaw($value);

        return $pairs === null ? null : array_map(static fn (string $raw): string => self::unquote($raw), $pairs);
    }

    /**
     * Like {@see parse()}, but keeps each value exactly as written — quotes
     * included — so a quoted string can be told apart from a bare variable.
     *
     * @return array<string, string>|null null when the value is not an array literal
     */
    public static function parseRaw(string $value): ?array
    {
        $body = self::unwrap($value);
        if ($body === null || preg_match(self::INLINE_VIEWHELPER, trim($value)) === 1) {
            return null;
        }

        if (trim($body) === '') {
            return [];
        }

        $pairs = [];
        foreach (self::splitTopLevel($body, ',') as $entry) {
            $parts = self::splitTopLevel($entry, ':', 2);
            if (count($parts) !== 2) {
                return null;
            }

            $key = self::unquote($parts[0]);
            if (preg_match(self::KEY, $key) !== 1) {
                return null;
            }

            $pairs[$key] = trim($parts[1]);
        }

        return $pairs;
    }

    /**
     * Returns the content between the outermost braces, or null when the value
     * is not a single braced expression (`{a} {b}` is two of them, not one).
     */
    private static function unwrap(string $value): ?string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) < 2 || $trimmed[0] !== '{' || !str_ends_with($trimmed, '}')) {
            return null;
        }

        // The opening brace must stay open until the very last character.
        $depth = 0;
        foreach (self::characters($trimmed) as $offset => $char) {
            $depth += self::depthChange($char);
            if ($depth === 0 && $offset !== strlen($trimmed) - 1) {
                return null;
            }
        }

        return substr($trimmed, 1, -1);
    }

    /**
     * Splits on the delimiter, ignoring occurrences inside quotes, braces or
     * parentheses — the latter keeps inline ViewHelpers such as
     * {@code {f:translate(key: 'x')}} in one piece.
     *
     * @return list<string>
     */
    private static function splitTopLevel(string $subject, string $delimiter, int $limit = 0): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        foreach (self::characters($subject) as $char) {
            if ($char === $delimiter && $depth === 0 && ($limit === 0 || count($parts) < $limit - 1)) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $depth += self::depthChange($char);
            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * How a character shifts the nesting depth. Quoted chunks arrive here whole
     * and never count.
     */
    private static function depthChange(string $char): int
    {
        return match ($char) {
            '{', '(' => 1,
            '}', ')' => -1,
            default => 0,
        };
    }

    /**
     * Walks the string character by character, yielding quoted sections as one
     * chunk so their contents never influence splitting or brace depth.
     *
     * @return Generator<int, string>
     */
    private static function characters(string $subject): Generator
    {
        $length = strlen($subject);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $subject[$offset];
            if ($char !== '"' && $char !== "'") {
                yield $offset => $char;
                continue;
            }

            $end = self::endOfQuote($subject, $offset);
            yield $offset => substr($subject, $offset, $end - $offset + 1);
            $offset = $end;
        }
    }

    /**
     * The offset of the closing quote matching the one at $start, or the last
     * offset of the string when the quote is never closed.
     */
    private static function endOfQuote(string $subject, int $start): int
    {
        $quote = $subject[$start];
        $length = strlen($subject);

        for ($offset = $start + 1; $offset < $length; $offset++) {
            if ($subject[$offset] === '\\') {
                $offset++;
                continue;
            }

            if ($subject[$offset] === $quote) {
                return $offset;
            }
        }

        return $length - 1;
    }

    /**
     * Removes one layer of surrounding quotes; the content is kept verbatim so
     * an embedded {@code {expression}} survives.
     */
    private static function unquote(string $value): string
    {
        $trimmed = trim($value);
        $first = $trimmed[0] ?? '';

        if (strlen($trimmed) >= 2 && ($first === '"' || $first === "'") && str_ends_with($trimmed, $first)) {
            return substr($trimmed, 1, -1);
        }

        return $trimmed;
    }
}
