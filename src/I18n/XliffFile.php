<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

use DOMDocument;
use DOMElement;

/**
 * Reads the labels of an XLIFF 1.2 language file and adds new ones.
 *
 * New units are inserted as text before {@code </body>}, indented like the
 * units already there, so the file's formatting — and therefore its diff —
 * stays untouched apart from the added lines.
 */
final class XliffFile
{
    private const NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';

    /**
     * @param array<string, string> $sources key => source text
     */
    private function __construct(
        public readonly string $path,
        public readonly ?string $content,
        public readonly array $sources,
    ) {
    }

    /**
     * Loads the file, or starts an empty one when it does not exist yet.
     *
     * @throws XliffException when the file exists but is not a readable XLIFF document
     */
    public static function open(string $path): self
    {
        if (!is_file($path)) {
            return new self($path, null, []);
        }

        $content = (string) file_get_contents($path);

        return new self($path, $content, self::readSources($path, $content));
    }

    public function exists(): bool
    {
        return $this->content !== null;
    }

    public function has(string $id): bool
    {
        return isset($this->sources[$id]);
    }

    /**
     * The file's content with the units added.
     *
     * @param list<TranslationUnit> $units
     * @param array{sourceLanguage: string, targetLanguage: ?string, original: string, product: string} $header
     *        used when the file is created
     */
    public function withUnits(array $units, array $header): string
    {
        $content = $this->content ?? $this->skeleton($header);

        $close = strrpos($content, '</body>');
        if ($close === false) {
            throw new XliffException(sprintf('No <body> element in %s.', $this->path));
        }

        $lineStart = (int) strrpos(substr($content, 0, $close), "\n") + 1;
        $bodyIndent = substr($content, $lineStart, $close - $lineStart);
        $sharesLine = trim($bodyIndent) !== '';
        if ($sharesLine) {
            // "</body>" shares its line with other content; start a new line.
            $lineStart = $close;
            $bodyIndent = '';
        }

        [$unitIndent, $childIndent] = $this->indentation($content, $bodyIndent);

        $added = '';
        foreach ($units as $unit) {
            $added .= $this->unit($unit, $unitIndent, $childIndent);
        }
        if ($sharesLine) {
            $added = "\n" . $added;
        }

        return substr($content, 0, $lineStart) . $added . substr($content, $lineStart);
    }

    private function unit(TranslationUnit $unit, string $indent, string $childIndent): string
    {
        $xml = sprintf('%s<trans-unit id="%s">' . "\n", $indent, $this->escapeAttribute($unit->id));
        $xml .= sprintf('%s<source>%s</source>' . "\n", $childIndent, $this->escapeText($unit->source));
        if ($unit->target !== null) {
            $xml .= sprintf('%s<target>%s</target>' . "\n", $childIndent, $this->escapeText($unit->target));
        }

        return $xml . $indent . '</trans-unit>' . "\n";
    }

    /**
     * The indentation of trans-unit lines and of their children, taken from the
     * file itself, or derived from the body's indentation.
     *
     * @return array{string, string}
     */
    private function indentation(string $content, string $bodyIndent): array
    {
        $step = '    ';
        $hasBody = preg_match('/^([ \t]*)<body/m', $content, $body) === 1;
        if ($hasBody && preg_match('/^([ \t]*)<file/m', $content, $file) === 1) {
            $derived = substr($body[1], strlen($file[1]));
            $step = $derived !== '' ? $derived : $step;
        }

        $unitIndent = preg_match('/^([ \t]*)<trans-unit/m', $content, $unit) === 1 ? $unit[1] : $bodyIndent . $step;
        $childIndent = preg_match('/^([ \t]*)<source/m', $content, $source) === 1 ? $source[1] : $unitIndent . $step;

        return [$unitIndent, $childIndent];
    }

    /**
     * @param array{sourceLanguage: string, targetLanguage: ?string, original: string, product: string} $header
     */
    private function skeleton(array $header): string
    {
        $target = $header['targetLanguage'] !== null
            ? sprintf(' target-language="%s"', $this->escapeAttribute($header['targetLanguage']))
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<xliff version="1.2" xmlns="' . self::NAMESPACE . '">' . "\n"
            . sprintf(
                '    <file source-language="%s"%s datatype="plaintext" original="%s" date="%s"'
                    . ' product-name="%s">' . "\n",
                $this->escapeAttribute($header['sourceLanguage']),
                $target,
                $this->escapeAttribute($header['original']),
                gmdate('Y-m-d\TH:i:s\Z'),
                $this->escapeAttribute($header['product']),
            )
            . '        <header/>' . "\n"
            . '        <body>' . "\n"
            . '        </body>' . "\n"
            . '    </file>' . "\n"
            . '</xliff>' . "\n";
    }

    /**
     * @return array<string, string>
     */
    private static function readSources(string $path, string $content): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($content, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            throw new XliffException(sprintf('Not a readable XLIFF file: %s', $path));
        }

        $sources = [];
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            $id = $unit->getAttribute('id');
            $source = self::child($unit, 'source');
            if ($id !== '' && $source !== null) {
                $sources[$id] = $source->textContent;
            }
        }

        return $sources;
    }

    private static function child(DOMElement $unit, string $name): ?DOMElement
    {
        foreach ($unit->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return $child;
            }
        }

        return null;
    }

    private function escapeText(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
