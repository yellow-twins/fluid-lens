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
        'wcag.label-for' => '<label for> with no matching id in the template',
        'wcag.html-lang' => '<html> without a lang attribute',
        'wcag.positive-tabindex' => 'Positive tabindex disrupts focus order',
        'wcag.table-header' => 'Data table without <th> headers',
        'wcag.aria-role' => 'Unknown WAI-ARIA role',
        'wcag.aria-attr' => 'Unknown aria-* attribute',
        'wcag.aria-hidden-focusable' => 'aria-hidden element that is still focusable',
        'wcag.aria-expanded-role' => 'aria-expanded on a non-interactive element',
        'wcag.tab-selected' => 'role="tab" without aria-selected',
        'wcag.tablist-tab' => 'role="tablist" without any role="tab"',
        'wcag.iframe-title' => '<iframe> without a title',
        'wcag.media-autoplay' => 'Media that autoplays sound',
        'wcag.meta-viewport' => 'Viewport meta tag that blocks zoom',
        'wcag.duplicate-id' => 'Duplicate id in one document',
        'wcag.heading-order' => 'Heading levels skipped',
        'wcag.empty-heading' => 'Heading with no text',
        'wcag.alt-filename' => 'alt text that is just a file name',
        'wcag.input-image-alt' => '<input type="image"> without alt text',
        'wcag.link-generic-text' => 'Non-descriptive link text ("read more")',
        'wcag.nested-interactive' => 'Interactive control nested in another',
        'wcag.fieldset-legend' => '<fieldset> without a <legend>',
        'wcag.list-structure' => '<ul>/<ol> with a non-<li> child',
        'wcag.th-empty' => 'Empty <th> header cell',
        'wcag.role-required-attr' => 'ARIA role missing its required state',
        'wcag.video-captions' => '<video> without a captions track',
        'wcag.marquee-blink' => '<marquee>/<blink> moving content',
        'wcag.lang-valid' => 'Invalid lang attribute value',
        'wcag.label-empty' => '<label> with no text',
        'wcag.aria-boolean' => 'Boolean ARIA attribute with an invalid value',
        'wcag.dir-valid' => 'Invalid dir attribute value',
        'wcag.meta-refresh' => '<meta http-equiv="refresh"> timed reload',
        'wcag.summary-details' => '<summary> outside a <details>',
        'wcag.scope-value' => 'Invalid table cell scope value',
        'wcag.abbr-title' => '<abbr> without a title (AAA)',
        'style.inline' => 'Inline style attribute',
        'partial.inline-svg' => 'Inline SVG to extract into an Icon partial',
        'image.prefer-fluid' => 'Raw <img> instead of <f:image>',
        'link.target-blank-rel' => 'target="_blank" without rel="noopener"',
        'markup.picture-img' => '<picture> without an <img> fallback',
        'markup.source-srcset' => '<source> in <picture> without srcset',
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
