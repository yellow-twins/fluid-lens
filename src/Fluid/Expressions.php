<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

/**
 * Finds the Fluid expressions in a piece of text or an attribute value.
 *
 * Only top-level {@code {…}} blocks are returned; nested braces (array literals,
 * inline ViewHelper arguments) stay part of their enclosing expression. Quotes
 * are honoured inside an expression only, because ordinary text is full of
 * apostrophes.
 */
final class Expressions
{
    /**
     * @return list<string> the content between the braces of each expression
     */
    public static function in(string $subject): array
    {
        $expressions = [];
        $depth = 0;
        $start = 0;
        $quote = null;
        $length = strlen($subject);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $subject[$offset];

            if ($quote !== null) {
                if ($char === '\\') {
                    $offset++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($depth > 0 && ($char === '"' || $char === "'")) {
                $quote = $char;
                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $offset + 1;
                }
                $depth++;
            } elseif ($char === '}' && $depth > 0) {
                $depth--;
                if ($depth === 0) {
                    $expressions[] = substr($subject, $start, $offset - $start);
                }
            }
        }

        return $expressions;
    }

    /**
     * Whether the value consists of exactly one expression and nothing else,
     * e.g. {@code {item.title}} but not {@code Hello {name}}.
     */
    public static function isSingle(string $value): bool
    {
        $trimmed = trim($value);
        $expressions = self::in($trimmed);

        return count($expressions) === 1 && $trimmed === '{' . $expressions[0] . '}';
    }
}
