<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Support\FluidArray;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Finds every {@code f:render} call of a template: the tag form
 * {@code <f:render partial="X" arguments="{…}" />} as well as the inline form
 * {@code {f:render(partial: 'X', arguments: {…})}}, also when chained with {@code ->}.
 */
final class RenderCallFinder
{
    private const INLINE_CALL = '/f:render\s*\(/';

    /**
     * @return list<RenderCall>
     */
    public function in(ParsedTemplate $template): array
    {
        $calls = [];
        $this->walk($template->file, $template->tree, 0, $calls);

        return $calls;
    }

    /**
     * @param list<RenderCall> $calls
     */
    private function walk(string $file, Node $node, int $line, array &$calls): void
    {
        if ($node->isElement()) {
            if ($node->name === 'f:comment') {
                return;
            }

            $line = $node->sourceRange?->startLine ?? $line;
            if ($node->name === 'f:render') {
                $calls[] = $this->fromTag($file, $line, $node);
            }

            foreach ($node->attributes as $value) {
                $this->collectInline($file, $line, $value, $calls);
            }
        }

        if ($node->type === NodeType::Text) {
            $this->collectInline($file, $line, $node->text, $calls);
        }

        foreach ($node->children() as $child) {
            $this->walk($file, $child, $line, $calls);
        }
    }

    private function fromTag(string $file, int $line, Node $node): RenderCall
    {
        return $this->build(
            $file,
            $line,
            $node->attribute('partial'),
            $node->attribute('section'),
            $node->attribute('arguments'),
        );
    }

    /**
     * @param list<RenderCall> $calls
     */
    private function collectInline(string $file, int $line, string $value, array &$calls): void
    {
        if (preg_match_all(self::INLINE_CALL, $value, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches[0] as $match) {
            $open = $match[1] + strlen($match[0]) - 1;
            $body = $this->parenthesisContent($value, $open);
            if ($body === null) {
                continue;
            }

            $parameters = FluidArray::parseRaw('{' . $body . '}') ?? [];
            $calls[] = $this->build(
                $file,
                $line,
                self::literal($parameters['partial'] ?? null),
                self::literal($parameters['section'] ?? null),
                isset($parameters['arguments']) ? self::argumentsValue($parameters['arguments']) : null,
            );
        }
    }

    private function build(string $file, int $line, ?string $partial, ?string $section, ?string $arguments): RenderCall
    {
        $raw = $arguments === null ? '' : trim($arguments);
        $passesAll = in_array($raw, ['{_all}', '_all'], true);
        $partial = $partial === null ? null : ltrim($partial, '/');

        return new RenderCall(
            $file,
            $line,
            $partial === '' ? null : $partial,
            $section === '' ? null : $section,
            $raw === '' || $passesAll ? null : self::attributeValues(FluidArray::parseRaw($raw)),
            $passesAll,
            $raw !== '',
        );
    }

    /**
     * Converts the raw values of a Fluid array literal into the form they take as
     * a tag attribute: a quoted string loses its quotes, a bare variable path
     * ({@code cart.products}) gets braces, everything else stays as written.
     *
     * @param array<string, string>|null $values
     *
     * @return array<string, string>|null
     */
    private static function attributeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_map(static fn (string $value): string => self::attributeValue($value), $values);
    }

    private static function attributeValue(string $raw): string
    {
        $value = trim($raw);
        $first = $value[0] ?? '';

        return match (true) {
            ($first === '"' || $first === "'") && strlen($value) >= 2 => substr($value, 1, -1),
            in_array(strtolower($value), ['true', 'false', 'null'], true) => '{' . strtolower($value) . '}',
            preg_match('/^[A-Za-z_][\w]*(\.[\w-]+)*$/', $value) === 1 => '{' . $value . '}',
            default => $value,
        };
    }

    private static function literal(?string $raw): ?string
    {
        return $raw === null ? null : self::attributeValue($raw);
    }

    /**
     * The arguments parameter of an inline call: {@code _all} unquoted, or an
     * array literal.
     */
    private static function argumentsValue(string $raw): string
    {
        $value = trim($raw);

        return $value === '_all' ? '{_all}' : $value;
    }

    /**
     * The text between the parenthesis at $open and its matching closing one,
     * skipping quoted strings; null when it is never closed.
     */
    private function parenthesisContent(string $subject, int $open): ?string
    {
        $depth = 0;
        $quote = null;
        $length = strlen($subject);

        for ($offset = $open; $offset < $length; $offset++) {
            $char = $subject[$offset];
            if ($quote !== null) {
                if ($char === '\\') {
                    $offset++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($subject, $open + 1, $offset - $open - 1);
                }
            }
        }

        return null;
    }
}
