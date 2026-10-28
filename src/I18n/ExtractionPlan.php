<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Everything an extraction would do: the fate of every hardcoded text and the
 * file changes that carry it out.
 */
final class ExtractionPlan
{
    /**
     * @param list<PlannedText> $texts
     * @param list<FileChange>  $changes templates first, then language files
     */
    public function __construct(
        public readonly array $texts,
        public readonly array $changes,
    ) {
    }

    /**
     * @return list<PlannedText>
     */
    public function extractable(): array
    {
        return array_values(array_filter($this->texts, static fn (PlannedText $text): bool => $text->isExtractable()));
    }

    /**
     * @return list<PlannedText>
     */
    public function manual(): array
    {
        return array_values(array_filter($this->texts, static fn (PlannedText $text): bool => !$text->isExtractable()));
    }
}
