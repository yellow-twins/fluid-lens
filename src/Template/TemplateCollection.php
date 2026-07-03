<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

/**
 * The result of collecting templates from a path: the successfully parsed
 * templates and the files that were skipped because they could not be parsed.
 */
final class TemplateCollection
{
    /**
     * @param list<ParsedTemplate>            $templates
     * @param list<array{file: string, reason: string}> $skipped
     */
    public function __construct(
        public readonly array $templates,
        public readonly array $skipped,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->templates === [];
    }
}
