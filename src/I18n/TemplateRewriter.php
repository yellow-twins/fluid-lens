<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Replaces hardcoded texts in a template's source with f:translate calls.
 *
 * Replacements are applied from the end of the source to its start, so each
 * one's offsets stay valid. Surrounding whitespace is never touched: the
 * replaced range is the text itself.
 */
final class TemplateRewriter
{
    /**
     * @param list<array{text: HardcodedText, target: Target, key: string}> $replacements
     */
    public function rewrite(string $source, array $replacements, TranslateStyle $style): string
    {
        usort($replacements, static fn (array $a, array $b): int => $b['text']->start <=> $a['text']->start);

        foreach ($replacements as ['text' => $text, 'target' => $target, 'key' => $key]) {
            $call = $this->call($text, $target, $key, $style);
            $source = substr($source, 0, $text->start) . $call . substr($source, $text->end);
        }

        return $source;
    }

    public function call(HardcodedText $text, Target $target, string $key, TranslateStyle $style): string
    {
        $arguments = $target->translateArguments($key);
        $variables = $text->message?->arguments ?? [];

        if ($text->kind === TextKind::Text && $style === TranslateStyle::Tag) {
            return $this->tag($arguments, $variables);
        }

        // Inside an attribute, string arguments take the quote the attribute does not use.
        $quote = $text->quote === "'" ? '"' : "'";

        return $this->inline($arguments, $variables, $quote);
    }

    /**
     * @param array<string, string> $arguments
     * @param list<string>          $variables
     */
    private function tag(array $arguments, array $variables): string
    {
        $attributes = '';
        foreach ($arguments as $name => $value) {
            $attributes .= sprintf(' %s="%s"', $name, $value);
        }
        if ($variables !== []) {
            $attributes .= sprintf(' arguments="%s"', $this->argumentArray($variables));
        }

        return '<f:translate' . $attributes . ' />';
    }

    /**
     * @param array<string, string> $arguments
     * @param list<string>          $variables
     */
    private function inline(array $arguments, array $variables, string $quote): string
    {
        $parts = [];
        foreach ($arguments as $name => $value) {
            $parts[] = sprintf('%s: %s%s%s', $name, $quote, $value, $quote);
        }
        if ($variables !== []) {
            $parts[] = 'arguments: ' . $this->argumentArray($variables);
        }

        return '{f:translate(' . implode(', ', $parts) . ')}';
    }

    /**
     * @param list<string> $variables
     */
    private function argumentArray(array $variables): string
    {
        $entries = [];
        foreach ($variables as $index => $variable) {
            $entries[] = sprintf('%d: %s', $index, $variable);
        }

        return '{' . implode(', ', $entries) . '}';
    }
}
