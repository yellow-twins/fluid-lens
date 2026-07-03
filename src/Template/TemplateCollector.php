<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

use YellowTwins\FluidLens\Parser\ParseException;
use YellowTwins\FluidLens\Parser\TemplateParser;

/**
 * Resolves a path into parsed templates, collecting any files that fail to parse
 * instead of aborting the whole run. Shared by the analysis commands.
 */
final class TemplateCollector
{
    private readonly TemplateFinder $finder;
    private readonly TemplateParser $parser;

    public function __construct(?TemplateFinder $finder = null, ?TemplateParser $parser = null)
    {
        $this->finder = $finder ?? new TemplateFinder();
        $this->parser = $parser ?? new TemplateParser();
    }

    public function collect(string $path): TemplateCollection
    {
        $templates = [];
        $skipped = [];

        foreach ($this->finder->find($path) as $file) {
            try {
                $templates[] = new ParsedTemplate($file, $this->parser->parseFile($file));
            } catch (ParseException $exception) {
                $skipped[] = ['file' => $file, 'reason' => $exception->getMessage()];
            }
        }

        return new TemplateCollection($templates, $skipped);
    }
}
