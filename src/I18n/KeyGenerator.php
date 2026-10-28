<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Proposes language-file keys: the template's prefix plus the first words of the
 * text in lowerCamelCase, e.g. "event.list.noEventsFound".
 *
 * Per language file it reuses the key of a label that is already there with the
 * same text, gives the same text the same key throughout a run, and appends
 * ".2", ".3", … when a key is taken by a different text.
 */
final class KeyGenerator
{
    private const MAX_WORDS = 4;

    private const TRANSLITERATION = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'å' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
        'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'æ' => 'ae',
        'œ' => 'oe', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E',
        'Ñ' => 'N', 'Ó' => 'O', 'Ô' => 'O', 'Ø' => 'O', 'Ú' => 'U', 'Û' => 'U',
    ];

    /** @var array<string, array<string, string>> file => key => label */
    private array $keys = [];

    /** @var array<string, array<string, string>> file => label => key */
    private array $labels = [];

    /**
     * Registers the labels a language file already holds.
     *
     * @param array<string, string> $units key => source text
     */
    public function know(string $file, array $units): void
    {
        foreach ($units as $key => $label) {
            $this->keys[$file][$key] = $label;
            $this->labels[$file][$label] ??= $key;
        }
    }

    /**
     * The key for the label in the target's file, and whether the key is new.
     *
     * @return array{string, bool}
     */
    public function keyFor(Target $target, string $label): array
    {
        $existing = $this->labels[$target->file][$label] ?? null;
        if ($existing !== null) {
            return [$existing, false];
        }

        $base = $target->keyPrefix . '.' . $this->slug($label);
        $key = $base;
        for ($suffix = 2; isset($this->keys[$target->file][$key]); $suffix++) {
            $key = $base . '.' . $suffix;
        }

        $this->keys[$target->file][$key] = $label;
        $this->labels[$target->file][$label] = $key;

        return [$key, true];
    }

    public function slug(string $label): string
    {
        $text = strtr((string) preg_replace('/%\d+\$s/', ' ', $label), self::TRANSLITERATION);
        $words = preg_split('/[^A-Za-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice($words, 0, self::MAX_WORDS);
        if ($words === []) {
            return 'text';
        }

        $slug = strtolower(array_shift($words));
        foreach ($words as $word) {
            $slug .= ucfirst(strtolower($word));
        }

        return ctype_digit($slug[0]) ? 'text' . ucfirst($slug) : $slug;
    }
}
