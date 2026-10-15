<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Index;

use YellowTwins\FluidLens\Fluid\NamespaceDeclarations;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * What the scanned templates already know about Fluid components: the component
 * definitions (files below a {@code Components/} directory), the namespace
 * prefixes that point at a component collection and how often each component
 * is used.
 *
 * Component definitions are never suggested again, and the dominant prefix and
 * collection directory tell the drafts where they belong.
 */
final class ComponentIndex
{
    private const COMPONENTS_DIRECTORY = '/Components/';

    private const COLLECTION_NAMESPACE = '/\\\\Components$/';

    /**
     * @param list<ExistingComponent> $components
     * @param array<string, string>   $namespaces prefix => PHP namespace of a component collection
     * @param array<string, int>      $prefixUses prefix => number of component tags using it
     */
    private function __construct(
        private readonly array $components,
        private readonly array $namespaces,
        private readonly array $prefixUses,
    ) {
    }

    /**
     * @param list<ParsedTemplate> $templates
     * @param list<string>         $configuredPrefixes prefixes declared in the configuration
     */
    public static function build(array $templates, array $configuredPrefixes = []): self
    {
        $namespaces = self::collectionNamespaces($templates);
        foreach ($configuredPrefixes as $prefix) {
            $namespaces[$prefix] ??= '';
        }

        $usages = self::countUsages($templates, array_keys($namespaces));

        $components = [];
        $prefixUses = [];
        foreach ($usages as $tag => $count) {
            $prefix = explode(':', $tag, 2)[0];
            $prefixUses[$prefix] = ($prefixUses[$prefix] ?? 0) + $count;
        }

        foreach ($templates as $template) {
            if (!self::isDefinition($template->file)) {
                continue;
            }

            $name = self::nameFromPath($template->file);
            $components[] = new ExistingComponent($name, $template, self::usagesOf($name, $usages));
        }

        usort(
            $components,
            static fn (ExistingComponent $a, ExistingComponent $b): int
                => [$b->usages, $a->name] <=> [$a->usages, $b->name],
        );

        return new self($components, $namespaces, $prefixUses);
    }

    public static function isDefinition(string $file): bool
    {
        return str_contains(str_replace('\\', '/', $file), self::COMPONENTS_DIRECTORY);
    }

    /**
     * The tag name of a component file, relative to its collection:
     * {@code Components/Schema/Event/Event.fluid.html} becomes {@code schema.event}.
     */
    public static function nameFromPath(string $file): string
    {
        $path = str_replace('\\', '/', $file);
        $start = (int) strrpos($path, self::COMPONENTS_DIRECTORY) + strlen(self::COMPONENTS_DIRECTORY);
        $relative = substr($path, $start);
        $relative = (string) preg_replace('/(\.fluid)?\.html$/', '', $relative);

        $segments = explode('/', $relative);
        $count = count($segments);
        if ($count >= 2 && $segments[$count - 1] === $segments[$count - 2]) {
            array_pop($segments);
        }

        return implode('.', array_map('lcfirst', $segments));
    }

    /**
     * @return list<ExistingComponent>
     */
    public function components(): array
    {
        return $this->components;
    }

    public function hasComponent(string $name): bool
    {
        foreach ($this->components as $component) {
            if (strtolower($component->name) === strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an element name is a component call, e.g. "st:icon".
     */
    public function isComponentTag(string $name): bool
    {
        $prefix = strtolower(explode(':', $name, 2)[0]);
        foreach (array_keys($this->namespaces) as $known) {
            if (strtolower((string) $known) === $prefix && str_contains($name, ':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The prefix most component tags use, falling back to the first declared one.
     */
    public function dominantPrefix(): ?string
    {
        if ($this->prefixUses !== []) {
            $uses = $this->prefixUses;
            arsort($uses);

            return (string) array_key_first($uses);
        }

        $prefix = array_key_first($this->namespaces);

        return $prefix === null ? null : (string) $prefix;
    }

    /**
     * The PHP namespace of a component prefix, empty when only configured.
     */
    public function namespaceOf(string $prefix): string
    {
        return $this->namespaces[$prefix] ?? '';
    }

    /**
     * The {@code Components/} directory of the existing definitions, where new
     * drafts belong; null when the project has no components yet.
     */
    public function collectionDirectory(): ?string
    {
        $directories = [];
        foreach ($this->components as $component) {
            $path = str_replace('\\', '/', $component->definition->file);
            $directory = substr($path, 0, (int) strrpos($path, self::COMPONENTS_DIRECTORY)) . '/Components';
            $directories[$directory] = ($directories[$directory] ?? 0) + 1;
        }

        if ($directories === []) {
            return null;
        }

        arsort($directories);

        return (string) array_key_first($directories);
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return array<string, string>
     */
    private static function collectionNamespaces(array $templates): array
    {
        $namespaces = [];
        foreach ($templates as $template) {
            foreach (NamespaceDeclarations::fromSource($template->source) as $prefix => $namespace) {
                if (preg_match(self::COLLECTION_NAMESPACE, $namespace) === 1) {
                    $namespaces[$prefix] ??= $namespace;
                }
            }
        }

        return $namespaces;
    }

    /**
     * @param list<ParsedTemplate> $templates
     * @param list<string>         $prefixes
     *
     * @return array<string, int> lowercase "prefix:name" => uses
     */
    private static function countUsages(array $templates, array $prefixes): array
    {
        if ($prefixes === []) {
            return [];
        }

        $alternatives = implode('|', array_map(static fn (string $p): string => preg_quote($p, '/'), $prefixes));
        $inline = '/(?<![\w-])(' . $alternatives . '):([\w.]+)\s*\(/i';

        $usages = [];
        foreach ($templates as $template) {
            foreach (Elements::all($template->tree) as $element) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($element->name, strtolower($prefix) . ':')) {
                        $usages[$element->name] = ($usages[$element->name] ?? 0) + 1;
                    }
                }
            }

            foreach (self::texts($template->tree) as $text) {
                if (preg_match_all($inline, $text, $matches, PREG_SET_ORDER) === false) {
                    continue;
                }
                foreach ($matches as $match) {
                    $tag = strtolower($match[1] . ':' . $match[2]);
                    $usages[$tag] = ($usages[$tag] ?? 0) + 1;
                }
            }
        }

        return $usages;
    }

    /**
     * @param array<string, int> $usages
     */
    private static function usagesOf(string $name, array $usages): int
    {
        $count = 0;
        foreach ($usages as $tag => $uses) {
            if (explode(':', $tag, 2)[1] === strtolower($name)) {
                $count += $uses;
            }
        }

        return $count;
    }

    /**
     * Every text node and attribute value of the tree.
     *
     * @return list<string>
     */
    private static function texts(Node $node): array
    {
        $texts = [];
        if ($node->type === NodeType::Text) {
            $texts[] = $node->text;
        }

        foreach ($node->attributes as $value) {
            $texts[] = $value;
        }

        foreach ($node->children() as $child) {
            array_push($texts, ...self::texts($child));
        }

        return $texts;
    }
}
