<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Detects which slider libraries a set of templates use — so a project that has
 * accumulated several different sliders can consolidate on one.
 */
final class SliderLibraryDetector
{
    /**
     * @param iterable<ParsedTemplate> $templates
     *
     * @return list<SliderUsage> one entry per library, most-used first
     */
    public function detect(iterable $templates): array
    {
        /** @var array<string, array<string, true>> $byLibrary */
        $byLibrary = [];
        foreach ($templates as $template) {
            foreach ($this->librariesIn($template->tree) as $library) {
                $byLibrary[$library][$template->file] = true;
            }
        }

        $usages = [];
        foreach ($byLibrary as $library => $files) {
            $usages[] = new SliderUsage($library, array_keys($files));
        }

        usort($usages, static fn (SliderUsage $a, SliderUsage $b): int => $b->fileCount() <=> $a->fileCount());

        return $usages;
    }

    /**
     * @return list<string>
     */
    private function librariesIn(Node $tree): array
    {
        $found = [];
        foreach (Elements::all($tree) as $element) {
            foreach ($this->classTokens($element) as $token) {
                $library = SliderLibrary::match($token);
                if ($library !== null) {
                    $found[$library] = true;
                }
            }
        }

        return array_keys($found);
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
