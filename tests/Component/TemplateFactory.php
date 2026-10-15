<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component;

use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Builds parsed templates from inline sources for the component tests.
 */
final class TemplateFactory
{
    /**
     * @param array<string, string> $sources file => template source
     *
     * @return list<ParsedTemplate>
     */
    public static function many(array $sources): array
    {
        $templates = [];
        foreach ($sources as $file => $source) {
            $templates[] = self::one($file, $source);
        }

        return $templates;
    }

    public static function one(string $file, string $source): ParsedTemplate
    {
        return new ParsedTemplate($file, (new TemplateParser())->parse($source), $source);
    }
}
