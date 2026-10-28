<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * Works out how the hardcoded texts move into language files: the file and key
 * of each one, the rewritten templates and the extended language files.
 *
 * Nothing is written here; the plan is shown as a report or a diff, or handed to
 * {@see ExtractionWriter}. Each rewritten template is checked by scanning it
 * again: unless exactly the extracted texts are gone, the template is left alone
 * and its texts are reported as not extracted.
 */
final class ExtractionPlanner
{
    private readonly KeyGenerator $keys;
    private readonly TemplateRewriter $rewriter;

    /** @var array<string, XliffFile|XliffException> */
    private array $files = [];

    public function __construct(
        private readonly TargetResolver $resolver,
        private readonly HardcodedTextFinder $finder,
        private readonly ExtractionSettings $settings = new ExtractionSettings(),
        ?KeyGenerator $keys = null,
        ?TemplateRewriter $rewriter = null,
    ) {
        $this->keys = $keys ?? new KeyGenerator();
        $this->rewriter = $rewriter ?? new TemplateRewriter();
    }

    /**
     * @param list<HardcodedText>   $texts
     * @param array<string, string> $sources template file => source
     */
    public function plan(array $texts, array $sources): ExtractionPlan
    {
        $planned = array_map(fn (HardcodedText $text): PlannedText => $this->assign($text), $texts);
        $style = $this->settings->style ?? TranslateStyle::prevailingIn($sources);

        $templateChanges = [];
        foreach ($this->byFile($planned) as $file => $indexes) {
            $change = $this->rewriteTemplate($file, $sources[$file] ?? '', $planned, $indexes, $style);
            if ($change === null) {
                foreach ($indexes as $index) {
                    $planned[$index] = $planned[$index]->withReason(
                        'rewriting the template did not pass the self-check; extract it by hand',
                    );
                }
                continue;
            }
            $templateChanges[] = $change;
        }

        return new ExtractionPlan($planned, array_merge($templateChanges, $this->languageFileChanges($planned)));
    }

    private function assign(HardcodedText $text): PlannedText
    {
        if ($text->message === null) {
            return new PlannedText($text);
        }

        $target = $this->resolver->resolve($text->file);
        if ($target === null) {
            return new PlannedText(
                $text,
                reason: 'no language file for this template; set "i18n.file" or --file',
            );
        }

        $file = $this->open($target->file);
        if ($file instanceof XliffException) {
            return new PlannedText($text, $target, reason: $file->getMessage());
        }

        [$key] = $this->keys->keyFor($target, $text->message->label);

        return new PlannedText($text, $target, $key, reused: $file->has($key));
    }

    private function open(string $path): XliffFile|XliffException
    {
        if (!isset($this->files[$path])) {
            try {
                $file = XliffFile::open($path);
                $this->keys->know($path, $file->sources);
                $this->files[$path] = $file;
            } catch (XliffException $exception) {
                $this->files[$path] = $exception;
            }
        }

        return $this->files[$path];
    }

    /**
     * @param list<PlannedText> $planned
     *
     * @return array<string, list<int>> template file => indexes of its extractable texts
     */
    private function byFile(array $planned): array
    {
        $files = [];
        foreach ($planned as $index => $text) {
            if ($text->isExtractable()) {
                $files[$text->text->file][] = $index;
            }
        }

        return $files;
    }

    /**
     * @param list<PlannedText> $planned
     * @param list<int>         $indexes
     */
    private function rewriteTemplate(
        string $file,
        string $source,
        array $planned,
        array $indexes,
        TranslateStyle $style,
    ): ?FileChange {
        $replacements = [];
        $extracted = [];
        foreach ($indexes as $index) {
            $text = $planned[$index];
            if ($text->target === null || $text->key === null) {
                continue;
            }
            $replacements[] = ['text' => $text->text, 'target' => $text->target, 'key' => $text->key];
            $extracted[$text->text->start] = true;
        }

        $rewritten = $this->rewriter->rewrite($source, $replacements, $style);

        return $this->passesSelfCheck($file, $source, $rewritten, $extracted)
            ? new FileChange($file, $source, $rewritten)
            : null;
    }

    /**
     * Scanning the rewritten template must find exactly what scanning the
     * original found, minus the extracted texts.
     *
     * @param array<int, true> $extracted start offsets of the extracted texts
     */
    private function passesSelfCheck(string $file, string $before, string $after, array $extracted): bool
    {
        $expected = [];
        foreach ($this->finder->find($file, $before) as $text) {
            if (!isset($extracted[$text->start])) {
                $expected[] = $this->fingerprint($text);
            }
        }

        $actual = array_map(
            fn (HardcodedText $text): string => $this->fingerprint($text),
            $this->finder->find($file, $after),
        );

        return $expected === $actual;
    }

    private function fingerprint(HardcodedText $text): string
    {
        return $text->kind->value . '|' . $text->context . '|' . $text->text;
    }

    /**
     * @param list<PlannedText> $planned
     *
     * @return list<FileChange>
     */
    private function languageFileChanges(array $planned): array
    {
        $units = [];
        $translations = [];
        $targets = [];

        foreach ($planned as $text) {
            $target = $text->target;
            $file = $target !== null ? ($this->files[$target->file] ?? null) : null;
            if (!$text->isExtractable() || $target === null || $text->key === null || !$file instanceof XliffFile) {
                continue;
            }

            $label = (string) $text->text->message?->label;
            $targets[$target->file] = $target;
            if (!$file->has($text->key)) {
                $units[$target->file][$text->key] = new TranslationUnit($text->key, $label);
            }
            if ($this->settings->writesLanguageFile()) {
                $translations[$target->file][$text->key] = new TranslationUnit($text->key, $label, $label);
            }
        }

        $changes = [];
        foreach ($units as $path => $fileUnits) {
            $changes[] = $this->change($this->files[$path], array_values($fileUnits), $targets[$path], null);
        }
        foreach ($translations as $path => $fileUnits) {
            $change = $this->translationChange($targets[$path], $fileUnits);
            if ($change !== null) {
                $changes[] = $change;
            }
        }

        return array_values(array_filter($changes));
    }

    /**
     * @param array<string, TranslationUnit> $units
     */
    private function translationChange(Target $target, array $units): ?FileChange
    {
        $language = (string) $this->settings->language;
        $file = $this->open($target->languageFile($language));
        if ($file instanceof XliffException) {
            return null;
        }

        $missing = array_values(
            array_filter($units, static fn (TranslationUnit $unit): bool => !$file->has($unit->id)),
        );

        return $missing === [] ? null : $this->change($file, $missing, $target, $language);
    }

    /**
     * @param list<TranslationUnit> $units
     */
    private function change(
        XliffFile|XliffException $file,
        array $units,
        Target $target,
        ?string $language,
    ): ?FileChange {
        if ($file instanceof XliffException) {
            return null;
        }

        $after = $file->withUnits($units, [
            'sourceLanguage' => $this->settings->sourceLanguage,
            'targetLanguage' => $language,
            'original' => $target->original(),
            'product' => $target->extensionKey ?? 'fluid-lens',
        ]);

        return new FileChange($file->path, $file->content, $after);
    }
}
