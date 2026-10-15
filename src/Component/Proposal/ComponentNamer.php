<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Proposal;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\Elements;

/**
 * Proposes a component name.
 *
 * From a Partial path the last segment is used, joined with its parent when it
 * alone says nothing ({@code Event/Teaser/Header} becomes {@code TeaserHeader}).
 * For copied markup the shared part of the root's class names is used, with
 * utility classes and BEM modifiers removed; failing that, the root element's
 * semantic role.
 */
final class ComponentNamer
{
    /**
     * Partial names that need their parent segment to mean something.
     */
    private const GENERIC_SEGMENTS = [
        'header', 'footer', 'item', 'items', 'list', 'card', 'detail', 'show', 'index', 'default', 'field',
        'body', 'main', 'content', 'wrapper', 'entry', 'row', 'single',
    ];

    /**
     * Class names that style rather than name a block.
     */
    private const UTILITY_CLASS = '/^(?:[pm][trblxy]?|col|row|d|g|gap|w|h|bg|text|font|flex|grid|align|justify|order'
        . '|container|cap|is|has|js|u)(?:-|$)|:/';

    private const ROLE_NAMES = [
        'figure' => 'Figure',
        'nav' => 'Navigation',
        'ul' => 'List',
        'ol' => 'List',
        'table' => 'Table',
        'a' => 'Link',
        'button' => 'Button',
        'article' => 'Card',
        'address' => 'Address',
        'header' => 'Header',
        'footer' => 'Footer',
        'form' => 'Form',
    ];

    /**
     * Names for a Partial from short to specific — {@code TeaserHeader},
     * {@code EventTeaserHeader} — so a clash takes the next one instead of a number.
     *
     * @return non-empty-list<string>
     */
    public function partialNameOptions(string $partial): array
    {
        $options = [$this->fromPartialName($partial)];
        $segments = array_values(array_filter(explode('/', $partial), static fn (string $s): bool => $s !== ''));
        for ($length = 1; $length <= count($segments); $length++) {
            $name = implode('', array_map([self::class, 'pascal'], array_slice($segments, -$length)));
            if (!in_array($name, $options, true) && strlen($name) >= strlen($options[0])) {
                $options[] = $name;
            }
        }

        return $options;
    }

    public function fromPartialName(string $partial): string
    {
        $segments = array_values(array_filter(explode('/', $partial), static fn (string $s): bool => $s !== ''));
        if ($segments === []) {
            return 'Partial';
        }

        $segment = (string) array_pop($segments);
        $name = self::pascal($segment);
        while ($segments !== [] && in_array(strtolower($segment), self::GENERIC_SEGMENTS, true)) {
            $segment = (string) array_pop($segments);
            $name = self::pascal($segment) . $name;
        }

        return $name;
    }

    /**
     * @param list<Node> $roots the root element of each copy
     */
    public function fromMarkup(array $roots): string
    {
        if ($roots === []) {
            return 'Block';
        }

        $fromClasses = $this->fromClasses($roots) ?? $this->fromClasses([$roots[0]]);
        if ($fromClasses !== null) {
            return $fromClasses;
        }

        // The first named block inside, e.g. "artist-single" inside a plain row —
        // a list of them when a loop lies in between.
        foreach (Elements::all($roots[0]) as $descendant) {
            $fromDescendant = $descendant === $roots[0] ? null : $this->fromClasses([$descendant]);
            if ($fromDescendant !== null) {
                return $fromDescendant . ($this->isLooped($descendant, $roots[0]) ? 'List' : '');
            }
        }

        return self::ROLE_NAMES[$roots[0]->name] ?? 'Block';
    }

    /**
     * Appends a number until the name is free.
     *
     * @param callable(string): bool $isTaken
     */
    public function unique(string $name, callable $isTaken): string
    {
        $candidate = $name;
        for ($suffix = 2; $isTaken($candidate); $suffix++) {
            $candidate = $name . $suffix;
        }

        return $candidate;
    }

    public static function tag(string $prefix, string $name): string
    {
        return $prefix . ':' . lcfirst($name);
    }

    public static function pascal(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $value) ?: [];

        return implode('', array_map(static fn (string $word): string => ucfirst($word), $words));
    }

    /**
     * @param list<Node> $roots
     */
    private function fromClasses(array $roots): ?string
    {
        $shared = null;
        foreach ($roots as $root) {
            $names = $this->blockNames((string) $root->attribute('class'));
            if ($names === []) {
                return null;
            }

            $shared = $shared === null ? $names : $this->commonSuffixes($shared, $names);
            if ($shared === []) {
                return null;
            }
        }

        return $shared === null ? null : self::pascal($shared[0]);
    }

    /**
     * The class names that name the block: utilities dropped, BEM element and
     * modifier parts cut off.
     *
     * @return list<string>
     */
    private function blockNames(string $class): array
    {
        $names = [];
        foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
            if ($token === '' || str_contains($token, '{') || preg_match(self::UTILITY_CLASS, $token) === 1) {
                continue;
            }

            $names[] = (string) preg_replace('/(__|--).*$/', '', $token);
        }

        return array_values(array_unique($names));
    }

    /**
     * For every pair of names, the longest shared trailing part of whole words:
     * {@code events-teasers-header} and {@code artists-teasers-header} share
     * {@code teasers-header}.
     *
     * @param list<string> $left
     * @param list<string> $right
     *
     * @return list<string>
     */
    private function commonSuffixes(array $left, array $right): array
    {
        $shared = [];
        foreach ($left as $a) {
            foreach ($right as $b) {
                $suffix = $this->commonWordSuffix($a, $b);
                if ($suffix !== '') {
                    $shared[] = $suffix;
                }
            }
        }

        usort($shared, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return array_values(array_unique($shared));
    }

    private function commonWordSuffix(string $a, string $b): string
    {
        $wordsA = array_reverse(explode('-', $a));
        $wordsB = array_reverse(explode('-', $b));
        $common = [];
        foreach ($wordsA as $index => $word) {
            if (($wordsB[$index] ?? null) !== $word) {
                break;
            }
            $common[] = $word;
        }

        return implode('-', array_reverse($common));
    }

    private function isLooped(Node $node, Node $boundary): bool
    {
        for ($parent = $node->parent(); $parent !== null && $parent !== $boundary; $parent = $parent->parent()) {
            if ($parent->name === 'f:for' || $parent->name === 'f:groupedfor') {
                return true;
            }
        }

        return false;
    }
}
