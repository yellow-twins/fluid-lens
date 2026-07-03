<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Base for consistency checks that recognise competing libraries by their
 * signature CSS classes. Subclasses provide the catalog of variant => class
 * roots; a class token matches a root as a whole token or a `root-`/`root__`
 * prefix.
 */
abstract class ClassSignatureCheck implements ConsistencyCheck
{
    /**
     * @return array<string, list<string>>
     */
    abstract protected function catalog(): array;

    public function analyze(array $templates): ConsistencyResult
    {
        /** @var array<string, array<string, true>> $byLabel */
        $byLabel = [];
        foreach ($templates as $template) {
            foreach ($this->labelsIn($template->tree) as $label) {
                $byLabel[$label][$template->file] = true;
            }
        }

        $usages = [];
        foreach ($byLabel as $label => $files) {
            $usages[] = new Usage($label, array_keys($files));
        }

        usort($usages, static fn (Usage $a, Usage $b): int => $b->fileCount() <=> $a->fileCount());

        return new ConsistencyResult($this->name(), $this->title(), $usages);
    }

    /**
     * @return list<string>
     */
    private function labelsIn(Node $tree): array
    {
        $found = [];
        foreach (Elements::all($tree) as $element) {
            foreach ($this->classTokens($element) as $token) {
                $label = $this->match($token);
                if ($label !== null) {
                    $found[$label] = true;
                }
            }
        }

        return array_keys($found);
    }

    private function match(string $token): ?string
    {
        foreach ($this->catalog() as $label => $roots) {
            foreach ($roots as $root) {
                if ($token === $root || str_starts_with($token, $root . '-') || str_starts_with($token, $root . '__')) {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function classTokens(Node $element): array
    {
        $class = $element->attribute('class');
        if ($class === null || trim($class) === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($class)) ?: []));
    }
}
