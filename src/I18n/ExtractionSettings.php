<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * How an extraction writes its results.
 */
final class ExtractionSettings
{
    public const DEFAULT_SOURCE_LANGUAGE = 'en';

    /**
     * @param TranslateStyle|null $style    null picks the form the templates use more often
     * @param string|null         $language the language the templates are written in, when it is not
     *                                      the source language: the text then also becomes the
     *                                      translation in e.g. de.locallang.xlf
     */
    public function __construct(
        public readonly ?TranslateStyle $style = null,
        public readonly ?string $language = null,
        public readonly string $sourceLanguage = self::DEFAULT_SOURCE_LANGUAGE,
    ) {
    }

    public function writesLanguageFile(): bool
    {
        return $this->language !== null && $this->language !== '' && $this->language !== $this->sourceLanguage;
    }
}
