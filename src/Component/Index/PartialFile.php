<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Index;

use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * A Partial template: the name it is rendered by and the resource root it
 * belongs to (the directory that contains its {@code Partials/} folder).
 */
final class PartialFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $root,
        public readonly ParsedTemplate $template,
    ) {
    }

    public function file(): string
    {
        return $this->template->file;
    }
}
