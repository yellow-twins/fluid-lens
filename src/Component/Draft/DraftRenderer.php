<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Draft;

use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\ComponentDraft;
use YellowTwins\FluidLens\Fluid\NamespaceDeclarations;

/**
 * Assembles a complete component file: the namespace wrapper, a comment saying
 * where the suggestion came from, the {@code <f:argument>} declarations and the
 * markup — in the layout TYPO3's own component examples use.
 */
final class DraftRenderer
{
    private const FLUID_NAMESPACE = 'TYPO3\\CMS\\Fluid\\ViewHelpers';

    private const TAG_PREFIX = '/<\/?([A-Za-z][\w-]*):[A-Za-z]/';

    private const INLINE_PREFIX = '/(?<![\w-])([A-Za-z][\w-]*):[A-Za-z][\w.]*\s*\(/';

    /**
     * @param list<ArgumentProposal> $arguments
     * @param list<string>           $comment    lines of the explanatory comment
     * @param array<string, string>  $namespaces prefix => PHP namespace, for prefixes other than f
     */
    public function render(
        string $name,
        array $arguments,
        string $markup,
        array $comment,
        array $namespaces,
        ?string $collectionDirectory,
    ): ComponentDraft {
        $lines = $this->wrapperOpening($markup, $namespaces);
        $lines[] = '<f:comment>';
        foreach ($comment as $line) {
            $lines[] = '    ' . $line;
        }
        $lines[] = '</f:comment>';

        foreach ($arguments as $argument) {
            $lines[] = $this->argument($argument);
        }

        $lines[] = '';
        $lines[] = $markup;
        $lines[] = '</html>';

        $directory = $collectionDirectory ?? 'Resources/Private/Components';

        return new ComponentDraft(
            sprintf('%s/%s/%s.fluid.html', $directory, $name, $name),
            implode("\n", $lines) . "\n",
        );
    }

    /**
     * @param array<string, string> $namespaces
     *
     * @return list<string>
     */
    private function wrapperOpening(string $markup, array $namespaces): array
    {
        $declarations = ['xmlns:f="' . NamespaceDeclarations::uriFor(self::FLUID_NAMESPACE) . '"'];
        foreach ($this->prefixesIn($markup) as $prefix) {
            if ($prefix === 'f') {
                continue;
            }

            $namespace = $namespaces[$prefix] ?? '';
            $uri = $namespace === ''
                ? 'TODO: namespace of ' . $prefix
                : NamespaceDeclarations::uriFor($namespace);
            $declarations[] = sprintf('xmlns:%s="%s"', $prefix, $uri);
        }
        $declarations[] = 'data-namespace-typo3-fluid="true">';

        $lines = ['<html ' . array_shift($declarations)];
        foreach ($declarations as $declaration) {
            $lines[] = '      ' . $declaration;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function prefixesIn(string $markup): array
    {
        $prefixes = [];
        foreach ([self::TAG_PREFIX, self::INLINE_PREFIX] as $pattern) {
            if (preg_match_all($pattern, $markup, $matches) !== false) {
                foreach ($matches[1] as $prefix) {
                    $prefixes[$prefix] = true;
                }
            }
        }

        return array_keys($prefixes);
    }

    private function argument(ArgumentProposal $argument): string
    {
        $line = sprintf('<f:argument name="%s" type="%s"', $argument->name, $argument->typeName());
        if ($argument->optional) {
            $line .= ' optional="{true}"';
        }
        if ($argument->default !== null) {
            $line .= sprintf(' default="%s"', str_replace('"', '&quot;', $argument->default));
        }
        if ($argument->description !== '') {
            $line .= sprintf(' description="%s"', str_replace('"', '&quot;', $argument->description));
        }

        return $line . ' />';
    }
}
