<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Renders a compact, indented preview of a subtree's elements so a reader can
 * recognise the duplicated structure at a glance.
 */
final class StructurePreview
{
    private const INDENT = '  ';
    private const MAX_LINES = 12;

    public function __construct(
        private readonly int $maxDepth = 4,
    ) {
    }

    /**
     * @return list<string>
     */
    public function render(Node $node): array
    {
        $lines = [];
        $this->walk($node, 0, $lines);

        if (count($lines) > self::MAX_LINES) {
            $lines = array_slice($lines, 0, self::MAX_LINES);
            $lines[] = '…';
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function walk(Node $node, int $depth, array &$lines): void
    {
        if (!$node->isElement()) {
            return;
        }

        $lines[] = str_repeat(self::INDENT, $depth) . $this->tag($node);

        if ($depth >= $this->maxDepth) {
            return;
        }

        foreach ($node->elementChildren() as $child) {
            $this->walk($child, $depth + 1, $lines);
        }
    }

    private function tag(Node $node): string
    {
        return sprintf('<%s>%s', $node->name, $this->classSuffix($node));
    }

    private function classSuffix(Node $node): string
    {
        $class = $node->attribute('class');
        if ($class === null || trim($class) === '') {
            return '';
        }

        return '.' . implode('.', preg_split('/\s+/', trim($class)) ?: []);
    }
}
