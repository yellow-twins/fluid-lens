<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

/**
 * Reads the ViewHelper namespace imports of a template from its raw source.
 *
 * Fluid knows two forms: the XML attribute on the wrapping tag,
 * {@code xmlns:st="http://typo3.org/ns/Vendor/Ext/Components"}, and the inline
 * declaration {@code {namespace st=Vendor\Ext\Components}}. The HTML5 parser
 * drops the attribute form, so this works on the source, not on the tree.
 */
final class NamespaceDeclarations
{
    private const XMLNS = '#xmlns:([A-Za-z][\w-]*)\s*=\s*["\']https?://typo3\.org/ns/([^"\']+)["\']#';

    private const INLINE = '/\{namespace\s+([A-Za-z][\w-]*)\s*=\s*([A-Za-z_][\w\\\\]*)\s*\}/';

    /**
     * @return array<string, string> prefix => PHP namespace (backslash separated)
     */
    public static function fromSource(string $source): array
    {
        $declarations = [];

        if (preg_match_all(self::XMLNS, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $declarations[$match[1]] = trim(str_replace('/', '\\', $match[2]), '\\');
            }
        }

        if (preg_match_all(self::INLINE, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $declarations[$match[1]] = trim($match[2], '\\');
            }
        }

        return $declarations;
    }

    /**
     * The XML namespace URI Fluid expects for a PHP namespace, as used in
     * {@code xmlns:*} attributes.
     */
    public static function uriFor(string $phpNamespace): string
    {
        return 'http://typo3.org/ns/' . str_replace('\\', '/', $phpNamespace);
    }
}
