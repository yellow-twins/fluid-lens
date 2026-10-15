<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Support\FluidArray;

/**
 * Finds the free variables of a subtree: everything it reads that it does not
 * define itself. These are the inputs a Partial receives from its caller, and
 * exactly what a component must declare as arguments, because a component only
 * sees what is passed to it.
 *
 * Fluid Lens does not run Fluid, so expressions are read with patterns. The
 * scanner errs on the side of reporting a variable: a missed input breaks a
 * component silently, an extra one is visible in the suggestion.
 */
final class VariableReferences
{
    /**
     * Elements whose content is not Fluid markup worth scanning.
     */
    private const SKIPPED_ELEMENTS = ['script', 'style', 'f:comment'];

    /**
     * Words that look like identifiers but are literals, operators or casts.
     */
    private const KEYWORDS = [
        'true', 'false', 'null', 'and', 'or', 'as', 'xor',
        'integer', 'int', 'float', 'boolean', 'bool', 'string', 'array', 'object',
    ];

    /**
     * Attributes that bind a new local variable, per (lowercased) ViewHelper.
     */
    private const BINDINGS = [
        'f:for' => ['as', 'key', 'iteration'],
        'f:groupedfor' => ['as', 'groupkey'],
        'f:variable' => ['name'],
        'f:cycle' => ['as'],
        'f:form.validationresults' => ['as'],
    ];

    private const QUOTED = '/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"/';

    private const VIEWHELPER_NAME = '/[A-Za-z][\w]*:[\w.]+(?=\s*\()/';

    private const ARRAY_KEY = '/([{,(]\s*)[A-Za-z_][\w.-]*\s*:(?!:)/';

    private const HINTED = '/\b(condition|each)\s*:\s*([A-Za-z_][\w.]*)/';

    private const IDENTIFIER = '/(?<![\w.$\\\\-])([A-Za-z_]\w*)((?:\.[\w-]+)*)/';

    /**
     * @return array<string, VariableUsage> root name => usage, local bindings removed
     */
    public function in(Node $node): array
    {
        /** @var array<string, array{paths: array<string, true>, hints: array<string, true>}> $found */
        $found = [];
        /** @var array<string, true> $bound */
        $bound = [];

        $this->walk($node, $found, $bound);

        $usages = [];
        foreach ($found as $root => $data) {
            if (isset($bound[$root])) {
                continue;
            }

            // Numeric-looking keys come back as integers from array_keys().
            $paths = array_map('strval', array_keys($data['paths']));
            sort($paths);
            $usages[$root] = new VariableUsage($root, $paths, array_map('strval', array_keys($data['hints'])));
        }
        ksort($usages);

        return $usages;
    }

    /**
     * @param array<string, array{paths: array<string, true>, hints: array<string, true>}> $found
     * @param array<string, true>                                                       $bound
     */
    private function walk(Node $node, array &$found, array &$bound): void
    {
        if ($node->type === NodeType::Text) {
            $this->scan($node->text, VariableUsage::HINT_OUTPUT, $found);

            return;
        }

        if ($node->isElement()) {
            if (in_array($node->name, self::SKIPPED_ELEMENTS, true)) {
                return;
            }

            $this->collectBindings($node, $bound);
            foreach ($node->attributes as $attribute => $value) {
                $this->scan($value, $this->hintFor($node->name, $attribute), $found);
            }
        }

        foreach ($node->children() as $child) {
            $this->walk($child, $found, $bound);
        }
    }

    /**
     * @param array<string, true> $bound
     */
    private function collectBindings(Node $node, array &$bound): void
    {
        foreach (self::BINDINGS[$node->name] ?? [] as $attribute) {
            $name = $node->attribute($attribute);
            if ($name !== null && preg_match('/^[A-Za-z_]\w*$/', $name) === 1) {
                $bound[$name] = true;
            }
        }

        if ($node->name === 'f:alias') {
            foreach (array_keys(FluidArray::parse($node->attribute('map') ?? '') ?? []) as $name) {
                $bound[$name] = true;
            }
        }
    }

    private function hintFor(string $element, string $attribute): string
    {
        if ($attribute === 'condition') {
            return VariableUsage::HINT_CONDITION;
        }

        if ($attribute === 'each' && ($element === 'f:for' || $element === 'f:groupedfor')) {
            return VariableUsage::HINT_EACH;
        }

        return VariableUsage::HINT_OUTPUT;
    }

    /**
     * @param array<string, array{paths: array<string, true>, hints: array<string, true>}> $found
     */
    private function scan(string $value, string $hint, array &$found): void
    {
        foreach (Expressions::in($value) as $expression) {
            if (preg_match('/^\s*namespace\s/', $expression) === 1) {
                continue;
            }

            $this->scanExpression($expression, $hint, $found);
        }
    }

    /**
     * @param array<string, array{paths: array<string, true>, hints: array<string, true>}> $found
     */
    private function scanExpression(string $expression, string $hint, array &$found): void
    {
        $code = (string) preg_replace(self::QUOTED, "''", $expression);

        // Inline ViewHelper arguments carry their own role: {f:if(condition: x)}.
        if (preg_match_all(self::HINTED, $code, $hinted, PREG_SET_ORDER) !== false) {
            foreach ($hinted as $match) {
                $this->record($found, $match[2], $match[1]);
            }
        }

        $code = (string) preg_replace(self::VIEWHELPER_NAME, ' ', $code);
        $code = (string) preg_replace(self::ARRAY_KEY, '$1', $code);

        if (preg_match_all(self::IDENTIFIER, $code, $matches, PREG_SET_ORDER) === false) {
            return;
        }

        foreach ($matches as $match) {
            if (in_array(strtolower($match[1]), self::KEYWORDS, true)) {
                continue;
            }

            $this->record($found, $match[1] . $match[2], $hint);
        }
    }

    /**
     * @param array<string, array{paths: array<string, true>, hints: array<string, true>}> $found
     */
    private function record(array &$found, string $path, string $hint): void
    {
        $root = explode('.', $path, 2)[0];
        if (in_array(strtolower($root), self::KEYWORDS, true)) {
            return;
        }

        // A role describes the value itself: looping over {item.tags} says
        // nothing about item, it is read like any other property.
        $found[$root] ??= ['paths' => [], 'hints' => []];
        $found[$root]['paths'][$path] = true;
        $found[$root]['hints'][$path === $root ? $hint : VariableUsage::HINT_OUTPUT] = true;
    }
}
