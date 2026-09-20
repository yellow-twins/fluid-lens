<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Decides, conservatively, whether an element exposes an accessible name.
 *
 * Fluid content is often dynamic, so this errs towards assuming a name exists
 * (text expressions, content-rendering ViewHelpers, a labelled image, or an
 * explicit aria-label/title all count) to avoid false positives — it only
 * reports the clear cases such as an icon-only link with nothing but an SVG
 * inside.
 */
final class AccessibleName
{
    private const LABELLING_ATTRIBUTES = ['aria-label', 'aria-labelledby', 'title'];

    /**
     * Tag name fragments of ViewHelpers that emit content of their own. Matched
     * in lowercase because the parser lowercases tag names (`f:cObject` arrives
     * as `f:cobject`).
     */
    private const TEXT_PRODUCING_VIEWHELPERS = ['translate', 'format.', 'render', 'cobject'];

    public static function isPresent(Node $element): bool
    {
        foreach (self::LABELLING_ATTRIBUTES as $attribute) {
            if (Attributes::present($element, $attribute)) {
                return true;
            }
        }

        // An unreadable aria="{attributes}" may well carry the label.
        if (TagAttributes::hasOpaqueArgument($element)) {
            return true;
        }

        return self::hasTextContent($element);
    }

    public static function hasTextContent(Node $node): bool
    {
        foreach ($node->children() as $child) {
            if ($child->type === NodeType::Text && trim($child->text) !== '') {
                return true;
            }

            if ($child->isElement() && self::elementProducesText($child)) {
                return true;
            }

            if ($child->isElement() && self::hasTextContent($child)) {
                return true;
            }
        }

        return false;
    }

    private static function elementProducesText(Node $element): bool
    {
        // A ViewHelper that renders text or foreign content (a partial, a
        // section, a TypoScript object) may produce the name; what it renders
        // cannot be known statically, so assume it does.
        foreach (self::TEXT_PRODUCING_VIEWHELPERS as $needle) {
            if (str_contains($element->name, $needle)) {
                return true;
            }
        }

        // An image with alt text contributes an accessible name to its container.
        return self::isImage($element) && Attributes::present($element, 'alt');
    }

    private static function isImage(Node $element): bool
    {
        return $element->name === 'img' || str_contains($element->name, 'image');
    }
}
