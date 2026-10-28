<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\I18n\ExtractionPlan;
use YellowTwins\FluidLens\I18n\PlannedText;

/**
 * Serialises hardcoded texts and their planned keys to stable JSON.
 */
final class JsonI18nReporter
{
    public function render(ExtractionPlan $plan, int $filesScanned): string
    {
        $payload = [
            'filesScanned' => $filesScanned,
            'extractableCount' => count($plan->extractable()),
            'manualCount' => count($plan->manual()),
            'texts' => array_map(fn (PlannedText $text): array => $this->text($text), $plan->texts),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function text(PlannedText $planned): array
    {
        $text = $planned->text;

        return [
            'file' => $text->file,
            'line' => $text->line,
            'kind' => $text->kind->value,
            'context' => $text->context,
            'text' => $text->text,
            'extractable' => $planned->isExtractable(),
            'label' => $text->message?->label,
            'arguments' => $text->message?->arguments ?? [],
            'key' => $planned->key,
            'existingKey' => $planned->reusesKey(),
            'languageFile' => $planned->isExtractable() ? $planned->target?->file : null,
            'reason' => $planned->blocker(),
        ];
    }
}
