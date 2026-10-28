<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Source;

/**
 * Splits a Fluid template's source into text, tags, expressions, comments and
 * raw content, keeping the exact byte offsets of each piece.
 *
 * The {@see \YellowTwins\FluidLens\Parser\TemplateParser} builds a tree for
 * structural analysis but cannot say where a node stands in the source. Rewriting
 * a template (replacing a text with a translation, for example) needs exactly
 * that, so this scanner reads the original text once, front to back, and never
 * normalises it.
 *
 * It is deliberately tolerant: a {@code <} or {@code {} that does not start
 * well-formed markup or a closed expression is kept as ordinary text, so broken
 * templates never abort a run.
 */
final class SourceScanner
{
    /**
     * Elements whose content is not markup. Fluid does not render the content of
     * f:comment, and script/style bodies are code, not text.
     */
    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'f:comment'];

    private const TAG_NAME = '/\G[A-Za-z][\w.:-]*/';

    private const ATTRIBUTE_NAME = '/\G[^\s=\/<>"\'{]+/';

    private const UNQUOTED_VALUE = '/\G[^\s>]*/';

    public function scan(string $source): ScannedSource
    {
        $segments = [];
        $length = strlen($source);
        $offset = 0;
        $textStart = 0;

        while ($offset < $length) {
            $piece = match ($source[$offset]) {
                '<' => $this->markupAt($source, $offset),
                '{' => $this->expressionAt($source, $offset),
                default => null,
            };

            if ($piece === null) {
                $offset++;
                continue;
            }

            $this->flushText($segments, $source, $textStart, $offset);
            $segments[] = $piece;
            $offset = $piece->end;

            $raw = $this->rawContentAfter($source, $piece);
            if ($raw !== null) {
                $segments[] = $raw;
                $offset = $raw->end;
            }
            $textStart = $offset;
        }

        $this->flushText($segments, $source, $textStart, $length);

        return new ScannedSource($source, $segments);
    }

    /**
     * @param list<Segment> $segments
     */
    private function flushText(array &$segments, string $source, int $start, int $end): void
    {
        if ($end > $start) {
            $segments[] = new Segment(SegmentType::Text, $start, $end, substr($source, $start, $end - $start));
        }
    }

    private function markupAt(string $source, int $offset): ?Segment
    {
        if (substr_compare($source, '<!--', $offset, 4) === 0) {
            return $this->delimited($source, $offset, '-->', SegmentType::Comment);
        }
        if (substr_compare($source, '<![CDATA[', $offset, 9) === 0) {
            // Fluid leaves CDATA content alone, so it is not markup either.
            return $this->delimited($source, $offset, ']]>', SegmentType::Raw);
        }
        if (preg_match('/\G<[!?]/', $source, $match, 0, $offset) === 1) {
            return $this->delimited($source, $offset, '>', SegmentType::Comment);
        }

        return $this->tagAt($source, $offset);
    }

    /**
     * A piece running from the offset to the end of the given delimiter, or to the
     * end of the source when the delimiter is missing.
     */
    private function delimited(string $source, int $offset, string $delimiter, SegmentType $type): Segment
    {
        $close = strpos($source, $delimiter, $offset + 2);
        $end = $close === false ? strlen($source) : $close + strlen($delimiter);

        return new Segment($type, $offset, $end, substr($source, $offset, $end - $offset));
    }

    private function tagAt(string $source, int $offset): ?Segment
    {
        $isClosing = ($source[$offset + 1] ?? '') === '/';
        $nameStart = $offset + ($isClosing ? 2 : 1);

        if (preg_match(self::TAG_NAME, $source, $match, 0, $nameStart) !== 1) {
            return null;
        }

        $name = $match[0];
        $parsed = $this->attributesFrom($source, $nameStart + strlen($name));
        if ($parsed === null) {
            return null;
        }

        [$attributes, $isSelfClosing, $end] = $parsed;
        $tag = new TagToken($name, $isClosing, $isSelfClosing, $isClosing ? [] : $attributes);

        return new Segment(SegmentType::Tag, $offset, $end, substr($source, $offset, $end - $offset), $tag);
    }

    /**
     * Reads attributes up to the end of the tag.
     *
     * @return array{list<AttributeToken>, bool, int}|null attributes, self-closing, end offset;
     *                                                     null when the tag never closes
     */
    private function attributesFrom(string $source, int $offset): ?array
    {
        $attributes = [];
        $length = strlen($source);

        while ($offset < $length) {
            $offset = $this->skipWhitespace($source, $offset);
            $char = $source[$offset] ?? '';

            if ($char === '>') {
                return [$attributes, false, $offset + 1];
            }
            if ($char === '/' && ($source[$offset + 1] ?? '') === '>') {
                return [$attributes, true, $offset + 2];
            }
            if ($char === '{') {
                // An expression in attribute position, e.g. <div {attributes}>.
                $offset = $this->expressionEnd($source, $offset) ?? $offset + 1;
                continue;
            }
            if ($char === '<') {
                // A ViewHelper inside the tag, e.g. <div<f:if condition="{x}"> class="y"</f:if>>:
                // step over it so the outer tag still ends at its own bracket.
                $offset = $this->tagAt($source, $offset)?->end ?? $offset + 1;
                continue;
            }
            if (preg_match(self::ATTRIBUTE_NAME, $source, $match, 0, $offset) !== 1) {
                $offset++;
                continue;
            }

            $attribute = $this->attributeAt($source, $offset, $match[0]);
            if ($attribute === null) {
                return null;
            }
            [$token, $offset] = $attribute;
            $attributes[] = $token;
        }

        return null;
    }

    /**
     * @return array{AttributeToken, int}|null the attribute and the offset after it;
     *                                         null for an unterminated quoted value
     */
    private function attributeAt(string $source, int $offset, string $name): ?array
    {
        $afterName = $offset + strlen($name);
        $cursor = $this->skipWhitespace($source, $afterName);

        if (($source[$cursor] ?? '') !== '=') {
            return [new AttributeToken($name, '', $afterName, $afterName, null), $afterName];
        }

        $valueStart = $this->skipWhitespace($source, $cursor + 1);
        $quote = $source[$valueStart] ?? '';

        if ($quote === '"' || $quote === "'") {
            $close = strpos($source, $quote, $valueStart + 1);
            if ($close === false) {
                return null;
            }
            $value = substr($source, $valueStart + 1, $close - $valueStart - 1);

            return [new AttributeToken($name, $value, $valueStart + 1, $close, $quote), $close + 1];
        }

        $valueEnd = $quote === '{'
            ? $this->expressionEnd($source, $valueStart) ?? $valueStart
            : $valueStart + (preg_match(self::UNQUOTED_VALUE, $source, $match, 0, $valueStart) === 1
                ? strlen($match[0])
                : 0);
        $value = substr($source, $valueStart, $valueEnd - $valueStart);

        return [new AttributeToken($name, $value, $valueStart, $valueEnd, null), $valueEnd];
    }

    private function skipWhitespace(string $source, int $offset): int
    {
        return $offset + strspn($source, " \t\n\r\f", $offset);
    }

    private function expressionAt(string $source, int $offset): ?Segment
    {
        $end = $this->expressionEnd($source, $offset);
        if ($end === null) {
            return null;
        }

        return new Segment(SegmentType::Expression, $offset, $end, substr($source, $offset, $end - $offset));
    }

    /**
     * The offset just past the brace that closes the expression opening at the
     * given offset, or null when it never closes. Quotes are honoured inside the
     * expression, so a brace in a string literal does not count.
     */
    private function expressionEnd(string $source, int $offset): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($cursor = $offset; $cursor < $length; $cursor++) {
            $char = $source[$cursor];

            if ($quote !== null) {
                if ($char === '\\') {
                    $cursor++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $cursor + 1;
                }
            }
        }

        return null;
    }

    /**
     * The raw body that follows an opening script, style or f:comment tag, up to
     * its closing tag (or the end of the source).
     */
    private function rawContentAfter(string $source, Segment $piece): ?Segment
    {
        $tag = $piece->tag;
        if ($tag === null || $tag->isClosing || $tag->isSelfClosing) {
            return null;
        }

        $name = $tag->lowercaseName();
        if (!in_array($name, self::RAW_TEXT_ELEMENTS, true)) {
            return null;
        }

        $close = stripos($source, '</' . $name, $piece->end);
        $end = $close === false ? strlen($source) : $close;
        if ($end === $piece->end) {
            return null;
        }

        return new Segment(SegmentType::Raw, $piece->end, $end, substr($source, $piece->end, $end - $piece->end));
    }
}
