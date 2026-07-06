<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Base for consistency checks that recognise competing libraries by signature
 * tokens on each element. By default the tokens are the element's CSS classes
 * *and* its attribute names, which lets attribute-driven libraries (Alpine's
 * `x-data`, htmx's `hx-get`, AOS's `data-aos`) be recognised alongside
 * class-based ones. A signature is either a token root (matched whole or as a
 * `root-`/`root__` prefix) or a regular expression (any signature starting with
 * `/`). Subclasses provide the catalog of variant => signatures.
 *
 * {@see ClassSignatureCheck} narrows the tokens to CSS classes only.
 */
abstract class SignatureCheck implements ConsistencyCheck
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
            foreach ($this->labelsIn($template) as $label) {
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
     * The signature tokens an element contributes. Defaults to its CSS classes
     * plus its attribute names.
     *
     * @return list<string>
     */
    protected function tokens(Node $element): array
    {
        return [...$this->classTokens($element), ...array_keys($element->attributes)];
    }

    /**
     * @return list<string>
     */
    protected function classTokens(Node $element): array
    {
        $class = $element->attribute('class');
        if ($class === null || trim($class) === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($class)) ?: []));
    }

    /**
     * @return list<string>
     */
    private function labelsIn(ParsedTemplate $template): array
    {
        $found = [];
        foreach (Elements::all($template->tree) as $element) {
            foreach ($this->tokens($element) as $token) {
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
        foreach ($this->catalog() as $label => $signatures) {
            foreach ($signatures as $signature) {
                if ($this->matchesSignature($token, $signature)) {
                    return $label;
                }
            }
        }

        return null;
    }

    private function matchesSignature(string $token, string $signature): bool
    {
        if (str_starts_with($signature, '/')) {
            return preg_match($signature, $token) === 1;
        }

        return $token === $signature
            || str_starts_with($token, $signature . '-')
            || str_starts_with($token, $signature . '__');
    }
}
