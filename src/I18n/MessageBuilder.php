<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

use YellowTwins\FluidLens\Source\Segment;
use YellowTwins\FluidLens\Source\SegmentType;

/**
 * Turns a run of text and Fluid expressions into a language-file label.
 *
 * Entities are decoded (the language file holds the text itself, and
 * f:translate escapes it again on output), source indentation collapses to
 * single spaces, and a plain variable such as {@code {event.title}} becomes a
 * positional placeholder {@code %1$s} passed in through {@code arguments}. Any
 * other expression — a ViewHelper call, a condition, a cast — is left for a
 * human, because moving it into a translation would change what it does.
 */
final class MessageBuilder
{
    private const VARIABLE = '/^[A-Za-z_]\w*(?:\.[\w-]+)*$/';

    private const WHITESPACE = '/[ \t\r\n\f]+/';

    /**
     * @param list<Segment> $segments text and expression segments, in order
     */
    public function build(array $segments): TextAnalysis
    {
        $display = '';
        $label = '';
        $plain = '';
        $arguments = [];
        $reason = null;

        foreach ($segments as $segment) {
            $display .= $segment->text;

            if ($segment->type === SegmentType::Tag || $segment->type === SegmentType::Comment) {
                // Markup inside an attribute value, e.g. title="<f:translate key='x' />".
                $reason ??= 'contains markup';
                continue;
            }

            if ($segment->type !== SegmentType::Expression) {
                $decoded = html_entity_decode($segment->text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $label .= $decoded;
                $plain .= $decoded;
                continue;
            }

            $variable = trim(substr($segment->text, 1, -1));
            if (preg_match(self::VARIABLE, $variable) !== 1) {
                $reason ??= sprintf('contains the Fluid expression %s, which is not a plain variable', $segment->text);
                continue;
            }

            $arguments[] = $variable;
            $label .= "\0" . count($arguments) . "\0";
        }

        $label = $this->collapse($label);
        $label = $arguments === [] ? $label : str_replace('%', '%%', $label);
        $label = (string) preg_replace('/\x00(\d+)\x00/', '%$1\$s', $label);

        return new TextAnalysis(
            $this->collapse($display),
            $this->collapse($plain),
            (int) preg_match_all('/\p{L}/u', $plain),
            $reason === null ? new Message($label, $arguments) : null,
            $reason,
        );
    }

    private function collapse(string $text): string
    {
        return trim((string) preg_replace(self::WHITESPACE, ' ', $text), " \t\r\n\f");
    }
}
