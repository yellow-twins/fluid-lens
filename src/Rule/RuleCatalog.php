<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

/**
 * One-line descriptions of the built-in rules, used to make `--list-rules`
 * self-documenting and to enrich the SARIF output. A test guarantees every rule
 * in {@see RuleSet::default()} has an entry here, so the two cannot drift apart.
 */
final class RuleCatalog
{
    /**
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'wcag.img-alt' => 'Image without an alt attribute',
        'wcag.link-name' => 'Link with no discernible text',
        'wcag.button-name' => 'Button with no discernible text',
        'wcag.form-label' => 'Form control that cannot be labelled',
        'wcag.html-lang' => '<html> without a lang attribute',
        'wcag.positive-tabindex' => 'Positive tabindex disrupts focus order',
        'wcag.table-header' => 'Data table without <th> headers',
        'wcag.aria-role' => 'Unknown WAI-ARIA role',
        'wcag.aria-attr' => 'Unknown aria-* attribute',
        'wcag.aria-hidden-focusable' => 'aria-hidden element that is still focusable',
        'wcag.iframe-title' => '<iframe> without a title',
        'wcag.media-autoplay' => 'Media that autoplays sound',
        'wcag.meta-viewport' => 'Viewport meta tag that blocks zoom',
        'wcag.duplicate-id' => 'Duplicate id in one document',
        'wcag.heading-order' => 'Heading levels skipped',
        'wcag.empty-heading' => 'Heading with no text',
        'style.inline' => 'Inline style attribute',
        'partial.inline-svg' => 'Inline SVG to extract into an Icon partial',
        'image.prefer-fluid' => 'Raw <img> instead of <f:image>',
        'link.target-blank-rel' => 'target="_blank" without rel="noopener"',
    ];

    public static function describe(string $name): ?string
    {
        return self::DESCRIPTIONS[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::DESCRIPTIONS;
    }
}
