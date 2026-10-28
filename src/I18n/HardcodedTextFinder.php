<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

use YellowTwins\FluidLens\Detector\UnionFind;
use YellowTwins\FluidLens\Source\ScannedSource;
use YellowTwins\FluidLens\Source\Segment;
use YellowTwins\FluidLens\Source\SegmentType;
use YellowTwins\FluidLens\Source\SourceScanner;
use YellowTwins\FluidLens\Source\TagToken;
use YellowTwins\FluidLens\Support\FluidArray;
use YellowTwins\FluidLens\Template\SuppressionScanner;

/**
 * Finds text written straight into a template: text content and the values of
 * attributes a visitor reads (title, alt, placeholder, aria-label, …).
 *
 * Text counts when, ignoring expressions, entities, digits and punctuation, at
 * least {@see FinderSettings::$minLetters} letters are left. Text interleaved
 * with inline markup ("Please <a>click here</a> to continue") is one sentence; it
 * is reported as such but never split up, because translating the pieces
 * separately loses the sentence.
 */
final class HardcodedTextFinder
{
    /**
     * Elements whose text is not prose for a visitor, or already translated.
     */
    private const SKIPPED_CONTENT = ['code', 'pre', 'kbd', 'samp', 'f:translate', 'f:cobject', 'f:argument'];

    private const INLINE_ELEMENTS = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'kbd', 'mark', 'q',
        's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr', 'f:translate',
    ];

    /** Fluid ViewHelper families that render inline, such as f:link.page or f:format.date. */
    private const INLINE_VIEWHELPER_PREFIXES = ['f:link.', 'f:format.'];

    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /** Elements whose start implicitly ends an open one of the same name. */
    private const SELF_ENDING = ['li', 'p', 'option', 'tr', 'td', 'th', 'dt', 'dd'];

    /** Attributes a visitor reads (or hears) on any element. */
    private const TRANSLATABLE_ATTRIBUTES = [
        'title', 'alt', 'placeholder', 'aria-label', 'aria-description', 'aria-roledescription',
        'aria-placeholder', 'aria-valuetext',
    ];

    /** Keys of the aria / additionalAttributes array arguments that hold visible text. */
    private const TRANSLATABLE_ARRAY_KEYS = [
        'aria' => ['label', 'description', 'roledescription', 'placeholder', 'valuetext'],
        'additionalattributes' => ['title', 'alt', 'placeholder', 'aria-label', 'aria-description'],
    ];

    /** A single "word" at least this long is a URL, hash or data, not prose. */
    private const MAX_WORD_LENGTH = 50;

    private const BUTTON_INPUT_TYPES = ['submit', 'button', 'reset'];

    private const META_TEXT_NAMES = [
        'description', 'og:title', 'og:description', 'twitter:title', 'twitter:description',
    ];

    private readonly SourceScanner $scanner;
    private readonly MessageBuilder $messages;
    private readonly SuppressionScanner $suppressions;

    /** @var list<array{name: string, id: int}> */
    private array $stack = [];

    private int $nextId = 0;

    /** @var list<Segment> */
    private array $run = [];

    /** @var list<array{segments: list<Segment>, path: list<int>, parent: string}> */
    private array $phrase = [];

    /** @var list<HardcodedText> */
    private array $found = [];

    private string $file = '';

    private ScannedSource $scanned;

    /** @var array<int, true> */
    private array $ignoredLines = [];

    public function __construct(
        private readonly FinderSettings $settings = new FinderSettings(),
        ?SourceScanner $scanner = null,
        ?MessageBuilder $messages = null,
        ?SuppressionScanner $suppressions = null,
    ) {
        $this->scanner = $scanner ?? new SourceScanner();
        $this->messages = $messages ?? new MessageBuilder();
        $this->suppressions = $suppressions ?? new SuppressionScanner();
        $this->scanned = new ScannedSource('', []);
    }

    /**
     * @return list<HardcodedText> in source order
     */
    public function find(string $file, string $source): array
    {
        $this->reset($file, $source);

        foreach ($this->scanned->segments as $segment) {
            match ($segment->type) {
                SegmentType::Text, SegmentType::Expression => $this->addToRun($segment),
                SegmentType::Tag => $this->visitTag($segment),
                SegmentType::Comment, SegmentType::Raw => $this->endPhrase(),
            };
        }
        $this->endPhrase();

        $found = $this->found;
        usort($found, static fn (HardcodedText $a, HardcodedText $b): int => $a->start <=> $b->start);

        return $found;
    }

    private function reset(string $file, string $source): void
    {
        $this->file = $file;
        $this->scanned = $this->scanner->scan($source);
        $this->ignoredLines = $this->suppressions->scan($source);
        $this->stack = [];
        $this->nextId = 0;
        $this->run = [];
        $this->phrase = [];
        $this->found = [];
    }

    private function addToRun(Segment $segment): void
    {
        if (!$this->isInSkippedContent()) {
            $this->run[] = $segment;
        }
    }

    private function visitTag(Segment $segment): void
    {
        $tag = $segment->tag;
        if ($tag === null) {
            return;
        }

        $this->endRun();
        if (!$this->isInline($tag->lowercaseName())) {
            $this->endPhrase();
        }

        if ($tag->isClosing) {
            $this->close($tag->lowercaseName());

            return;
        }

        if (!$this->isInSkippedContent()) {
            $this->inspectAttributes($tag);
        }
        $this->open($tag);
    }

    private function open(TagToken $tag): void
    {
        $name = $tag->lowercaseName();
        if ($tag->isSelfClosing || in_array($name, self::VOID_ELEMENTS, true)) {
            return;
        }

        $top = array_slice($this->stack, -1)[0] ?? null;
        if ($top !== null && $top['name'] === $name && in_array($name, self::SELF_ENDING, true)) {
            array_pop($this->stack);
        }

        $this->stack[] = ['name' => $name, 'id' => ++$this->nextId];
    }

    private function close(string $name): void
    {
        for ($index = count($this->stack) - 1; $index >= 0; $index--) {
            if ($this->stack[$index]['name'] === $name) {
                $this->stack = array_slice($this->stack, 0, $index);

                return;
            }
        }
    }

    private function isInline(string $name): bool
    {
        if (in_array($name, self::INLINE_ELEMENTS, true)) {
            return true;
        }

        foreach (self::INLINE_VIEWHELPER_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isInSkippedContent(): bool
    {
        foreach ($this->stack as $frame) {
            if (in_array($frame['name'], self::SKIPPED_CONTENT, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Closes the current run of text; a run with letters joins the phrase.
     */
    private function endRun(): void
    {
        if ($this->run === []) {
            return;
        }

        $analysis = $this->messages->build($this->run);
        if ($this->isProse($analysis, $this->parentName())) {
            $this->phrase[] = [
                'segments' => $this->run,
                'path' => array_column($this->stack, 'id'),
                'parent' => $this->parentName(),
            ];
        }
        $this->run = [];
    }

    /**
     * Closes the phrase: runs whose elements nest inside one another form one
     * sentence; every other run stands alone.
     */
    private function endPhrase(): void
    {
        $this->endRun();

        foreach ($this->groupSentences($this->phrase) as $group) {
            if (count($group) === 1) {
                $this->reportRun($group[0]['segments'], $group[0]['parent']);
            } else {
                $this->reportSentence($group);
            }
        }

        $this->phrase = [];
    }

    /**
     * @param list<array{segments: list<Segment>, path: list<int>, parent: string}> $runs
     *
     * @return list<list<array{segments: list<Segment>, path: list<int>, parent: string}>>
     */
    private function groupSentences(array $runs): array
    {
        if ($runs === []) {
            return [];
        }

        // Transitively: in "<span>a</span> b <strong>c</strong>", b links a and c into one sentence.
        $sentences = new UnionFind(count($runs));
        foreach ($runs as $index => $run) {
            for ($other = 0; $other < $index; $other++) {
                if ($this->isNested($runs[$other]['path'], $run['path'])) {
                    $sentences->union($other, $index);
                }
            }
        }

        $groups = [];
        foreach ($sentences->clusters() as $members) {
            sort($members);
            $groups[] = array_map(static fn (int $member): array => $runs[$member], $members);
        }

        return $groups;
    }

    /**
     * Whether one element path is an ancestor-or-self of the other, i.e. the two
     * texts belong to the same sentence rather than to sibling elements.
     *
     * @param list<int> $first
     * @param list<int> $second
     */
    private function isNested(array $first, array $second): bool
    {
        $shorter = count($first) <= count($second) ? $first : $second;
        $longer = $shorter === $first ? $second : $first;

        return array_slice($longer, 0, count($shorter)) === $shorter;
    }

    /**
     * @param list<Segment> $segments
     */
    private function reportRun(array $segments, string $parent): void
    {
        $trimmed = $this->trim($segments);
        if ($trimmed === null) {
            return;
        }
        [$start, $end, $inner] = $trimmed;

        $analysis = $this->messages->build($inner);
        if ($this->settings->ignores($analysis->plainText)) {
            return;
        }

        $this->report(TextKind::Text, $parent, $analysis, $start, $end);
    }

    /**
     * @param list<array{segments: list<Segment>, path: list<int>, parent: string}> $group
     */
    private function reportSentence(array $group): void
    {
        $first = $group[0]['segments'];
        $last = array_slice($group, -1)[0]['segments'];
        $start = $first[0]->start;
        $end = array_slice($last, -1)[0]->end;

        $markup = substr($this->scanned->source, $start, $end - $start);
        $display = trim((string) preg_replace('/\s+/', ' ', strip_tags($markup)));
        if ($this->settings->ignores($display)) {
            return;
        }

        $analysis = new TextAnalysis(
            $display,
            $display,
            0,
            null,
            'a sentence with inline markup; translate it as a whole by hand',
        );
        $this->report(TextKind::Text, $group[0]['parent'], $analysis, $start, $end);
    }

    /**
     * Narrows the run to the range without leading and trailing whitespace.
     *
     * @param list<Segment> $segments
     *
     * @return array{int, int, list<Segment>}|null
     */
    private function trim(array $segments): ?array
    {
        $source = $this->scanned->source;
        $start = $segments[0]->start;
        $end = array_slice($segments, -1)[0]->end;

        $start += strspn($source, " \t\r\n\f", $start, $end - $start);
        while ($end > $start && strpbrk($source[$end - 1], " \t\r\n\f") !== false) {
            $end--;
        }
        if ($end <= $start) {
            return null;
        }

        $inner = [];
        foreach ($segments as $segment) {
            $from = max($segment->start, $start);
            $to = min($segment->end, $end);
            if ($to > $from) {
                $inner[] = new Segment($segment->type, $from, $to, substr($source, $from, $to - $from));
            }
        }

        return [$start, $end, $inner];
    }

    private function inspectAttributes(TagToken $tag): void
    {
        foreach ($tag->attributes as $attribute) {
            $name = $attribute->lowercaseName();

            if (isset(self::TRANSLATABLE_ARRAY_KEYS[$name]) && str_contains($tag->name, ':')) {
                $this->inspectArrayArgument($tag, $name, $attribute->value, $attribute->valueStart);
                continue;
            }

            if (!$this->isTranslatableAttribute($tag, $name)) {
                continue;
            }

            $segments = $this->scanner->scan($attribute->value)->segments;
            $analysis = $this->messages->build($segments);
            if (!$this->isProse($analysis, $tag->lowercaseName()) || $this->settings->ignores($analysis->plainText)) {
                continue;
            }

            if ($attribute->quote === null) {
                $analysis = new TextAnalysis(
                    $analysis->display,
                    $analysis->plainText,
                    $analysis->letters,
                    null,
                    'an unquoted attribute value; quote it first',
                );
            }

            $this->report(
                TextKind::Attribute,
                $tag->name . ' ' . $attribute->name,
                $analysis,
                $attribute->valueStart,
                $attribute->valueEnd,
                $attribute->quote,
            );
        }
    }

    private function isTranslatableAttribute(TagToken $tag, string $name): bool
    {
        if (in_array($name, [...self::TRANSLATABLE_ATTRIBUTES, ...$this->settings->attributes], true)) {
            return true;
        }

        $element = $tag->lowercaseName();

        return match ($name) {
            'value' => $element === 'f:form.submit'
                || ($element === 'input'
                    && in_array(strtolower((string) $tag->attributeValue('type')), self::BUTTON_INPUT_TYPES, true)),
            'label' => in_array($element, ['option', 'optgroup', 'track'], true),
            'prependoptionlabel' => $element === 'f:form.select',
            'content' => $element === 'meta' && $this->isTextMeta($tag),
            default => false,
        };
    }

    private function isTextMeta(TagToken $tag): bool
    {
        $name = strtolower((string) ($tag->attributeValue('name') ?? $tag->attributeValue('property')));

        return in_array($name, self::META_TEXT_NAMES, true);
    }

    /**
     * Literal texts inside {@code aria="{label: '…'}"} and similar array arguments.
     * Rewriting a value inside a Fluid array is left for a human in this version.
     */
    private function inspectArrayArgument(TagToken $tag, string $argument, string $value, int $offset): void
    {
        $pairs = FluidArray::parseRaw($value);
        if ($pairs === null) {
            return;
        }

        foreach ($pairs as $key => $raw) {
            if (!in_array(strtolower($key), self::TRANSLATABLE_ARRAY_KEYS[$argument], true)) {
                continue;
            }
            $quote = $raw[0] ?? '';
            if ($quote !== '"' && $quote !== "'") {
                continue;
            }

            // Quotes nested in the argument are escaped: aria="{label: '{f:translate(key: \'x\')}'}".
            $text = str_replace(['\\\'', '\\"'], ["'", '"'], substr($raw, 1, -1));
            $analysis = $this->messages->build($this->scanner->scan($text)->segments);
            if (!$this->isProse($analysis, $tag->lowercaseName()) || $this->settings->ignores($analysis->plainText)) {
                continue;
            }

            $this->report(
                TextKind::Attribute,
                sprintf('%s %s.%s', $tag->name, $argument, $key),
                new TextAnalysis(
                    $analysis->display,
                    $analysis->plainText,
                    $analysis->letters,
                    null,
                    'a literal inside a Fluid array argument; use {f:translate(…)} there by hand',
                ),
                $offset,
                $offset + strlen($value),
            );
        }
    }

    /**
     * Whether the text reads as words for a visitor rather than as a value the
     * template assembles: an identifier glued to a variable ({@code {prefix}Label}),
     * a code such as "ST-2026", a long token such as a URL or base64 data, or a
     * single token stored in f:variable.
     */
    private function isProse(TextAnalysis $analysis, string $parent): bool
    {
        if ($analysis->letters < $this->settings->minLetters) {
            return false;
        }
        $isOneToken = preg_match('/\s/u', $analysis->plainText) !== 1;
        if ($isOneToken && mb_strlen($analysis->plainText) >= self::MAX_WORD_LENGTH) {
            return false;
        }
        if (preg_match('/\}\p{L}|\p{L}\{/u', $analysis->display) === 1) {
            return false;
        }
        $isCode = preg_match('/^[\p{Lu}\d\W_]+$/u', $analysis->plainText) === 1
            && preg_match('/\d/', $analysis->plainText) === 1;
        if ($isCode) {
            return false;
        }

        return $parent !== 'f:variable' || str_contains($analysis->plainText, ' ');
    }

    private function parentName(): string
    {
        return array_slice($this->stack, -1)[0]['name'] ?? '(root)';
    }

    private function report(
        TextKind $kind,
        string $context,
        TextAnalysis $analysis,
        int $start,
        int $end,
        ?string $quote = null,
    ): void {
        $line = $this->scanned->lineOf($start);
        if (isset($this->ignoredLines[$line])) {
            return;
        }

        $this->found[] = new HardcodedText(
            $this->file,
            $line,
            $kind,
            $context,
            $analysis->display,
            $analysis->message,
            $analysis->reason,
            $start,
            $end,
            $quote,
        );
    }
}
