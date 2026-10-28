<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Baseline;

use YellowTwins\FluidLens\I18n\HardcodedText;

/**
 * A record of hardcoded texts a project accepts for now, so `i18n` only fails
 * on texts added later.
 *
 * A text is identified by its file, kind, context and wording — not its line,
 * so edits that shift lines do not churn the baseline. The same text may recur
 * up to the recorded count; one more surfaces.
 */
final class I18nBaseline
{
    private const VERSION = 1;

    /**
     * @param array<string, int> $texts identity => count
     */
    public function __construct(
        private readonly array $texts,
    ) {
    }

    /**
     * @param list<HardcodedText> $texts
     */
    public static function fromTexts(array $texts): self
    {
        $counts = [];
        foreach ($texts as $text) {
            $key = self::key($text);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return new self($counts);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{texts?: array<string, int>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['texts'] ?? []);
    }

    public function toJson(): string
    {
        return json_encode(
            ['version' => self::VERSION, 'texts' => $this->texts],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function count(): int
    {
        return array_sum($this->texts);
    }

    /**
     * Returns only the texts not already covered by the baseline.
     *
     * @param list<HardcodedText> $texts
     *
     * @return list<HardcodedText>
     */
    public function filter(array $texts): array
    {
        $remaining = $this->texts;
        $kept = [];

        foreach ($texts as $text) {
            $key = self::key($text);
            if (($remaining[$key] ?? 0) > 0) {
                $remaining[$key]--;
                continue;
            }

            $kept[] = $text;
        }

        return $kept;
    }

    private static function key(HardcodedText $text): string
    {
        return implode('|', [$text->file, $text->kind->value, $text->context, $text->text]);
    }
}
