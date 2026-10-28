<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * How extracted text content is written back: as {@code <f:translate … />} or as
 * {@code {f:translate(…)}}. Attribute values always use the inline form.
 */
enum TranslateStyle: string
{
    case Tag = 'tag';
    case Inline = 'inline';

    /**
     * The form the given templates use more often; the tag form on a tie.
     *
     * @param iterable<string> $sources
     */
    public static function prevailingIn(iterable $sources): self
    {
        $tags = 0;
        $inline = 0;
        foreach ($sources as $source) {
            $tags += substr_count($source, '<f:translate');
            $inline += substr_count($source, '{f:translate(');
        }

        return $inline > $tags ? self::Inline : self::Tag;
    }
}
