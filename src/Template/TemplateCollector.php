<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Template;

use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Parser\TreePruner;

/**
 * Resolves a path into parsed templates, applying inline suppression markers and
 * collecting any unreadable files instead of aborting the whole run. Shared by
 * the analysis commands.
 */
final class TemplateCollector
{
    private readonly TemplateFinder $finder;
    private readonly TemplateParser $parser;
    private readonly SuppressionScanner $suppressions;
    private readonly TreePruner $pruner;

    public function __construct(
        ?TemplateFinder $finder = null,
        ?TemplateParser $parser = null,
        ?SuppressionScanner $suppressions = null,
        ?TreePruner $pruner = null,
    ) {
        $this->finder = $finder ?? new TemplateFinder();
        $this->parser = $parser ?? new TemplateParser();
        $this->suppressions = $suppressions ?? new SuppressionScanner();
        $this->pruner = $pruner ?? new TreePruner();
    }

    /**
     * Collects templates from several paths into one collection, de-duplicating
     * files that more than one path resolves to.
     *
     * @param list<string> $paths
     */
    public function collectPaths(array $paths): TemplateCollection
    {
        $templates = [];
        $skipped = [];
        $seen = [];

        foreach ($paths as $path) {
            $collection = $this->collect($path);
            foreach ($collection->templates as $template) {
                if (!isset($seen[$template->file])) {
                    $seen[$template->file] = true;
                    $templates[] = $template;
                }
            }
            foreach ($collection->skipped as $skip) {
                $skipped[] = $skip;
            }
        }

        return new TemplateCollection($templates, $skipped);
    }

    public function collect(string $path): TemplateCollection
    {
        $templates = [];
        $skipped = [];

        foreach ($this->finder->find($path) as $file) {
            $source = @file_get_contents($file);
            if ($source === false) {
                $skipped[] = ['file' => $file, 'reason' => 'could not be read'];
                continue;
            }

            $tree = $this->parser->parse($source);
            $tree = $this->pruner->prune($tree, $this->suppressions->scan($source));
            $templates[] = new ParsedTemplate($file, $tree);
        }

        return new TemplateCollection($templates, $skipped);
    }
}
