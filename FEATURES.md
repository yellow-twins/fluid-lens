# Feature backlog

Ideas not yet built. Grouped by area. Not commitments — a place so good ideas
aren't lost.

## Consistency checks

The `consistency` command runs project-wide checks that fail when competing
implementations are mixed. A new check is one class extending
`ClassSignatureCheck` (or implementing `ConsistencyCheck`) plus a registry entry.
Already shipped: `sliders`, `icons`, `css`, `js-framework`, `lightbox`,
`animation`, `lazyload`, `maps`, `video-player`, `grid`, `tooltip`,
`cookie-consent`.

The class/attribute-signature cluster is now complete. Note: Bootstrap tooltips
were deliberately left out of `tooltip` — `data-bs-toggle` cannot be told apart
from a dropdown/modal without the runtime attribute value, which a static check
does not have.

Fluid-specific consistency — shipped: `namespace-style`, `render-style`,
`translate-style`, `image-approach`, `link-approach`. These inspect the parse
tree (element names for the tag form, opaque `{...}` text/attribute values for
the inline form) rather than CSS classes. `image-approach`/`link-approach` only
count a raw `<img>`/`<a>` when its `src`/`href` is a Fluid expression, so static
assets and external links don't create false mixes.

Still open:

- **Icon rendering method** — inline `<svg>` vs icon font vs `<img>`. Deliberately
  *not* built: distinguishing an SVG/`<img>` icon from an illustration or photo
  is not statically decidable, so it would be guesswork. (Which icon *set* is
  mixed is already covered by `icons`.)

Explicitly out of scope for now (per project decision): date/time format
consistency, QR/chart libraries.

## Accessibility (WCAG)

- Colour contrast, runtime focus order, reflow, motion — need a rendered page;
  keep pointing users to axe/Lighthouse (the honest "needs runtime check" note).

Shipped since: `autocomplete` token validation (1.3.5), `lang`/`xml:lang`
mismatch, duplicate `accesskey`, `target="_blank"` new-tab purpose (3.2.5 AAA),
orphaned `aria-controls` / `aria-labelledby` / `aria-describedby` targets and
unlabelled navigation landmarks (all conservative notices — cross-partial FP
risk, same stance as `label-for`).

- **`wcag.form-label`: recognise implicit labels.** A control nested inside a
  `<label>` (`<label>Text <input …></label>`) is correctly labelled, but the rule
  only looks for `id`/`aria-label`/`aria-labelledby`/`title` on the control and
  reports a false positive. Fix: walk up `$node->parent()` and accept an
  ancestor `<label>` with non-empty text (reuse `Support/Text`); Fluid nodes
  between control and label (`<f:if>`, `<f:for>`) must not break the chain.
  Found in a real project (giveaway form: honeypot + consent checkbox), where it
  was worked around with explicit `id`/`for`.

- **Ignore `<f:comment>` content in the lint rules.** Markup mentioned in a
  comment (e.g. "escaped title or <img>") is parsed as a real element and
  reported (`wcag.img-alt` error on a comment line). Comments are not output, so
  every rule should skip the subtree of `f:comment` (and `<!--…-->` is already
  ignored). Found in a real project, worked around by rewording the comment.

Resolved: the `aria` / `data` / `additionalAttributes` array arguments of
TYPO3's tag-based ViewHelpers are expanded before the rules run, so an element
labelled through them is no longer reported as unlabelled. Open follow-up: the
same expansion is not applied to the consistency checks, which deliberately
compare the literal syntax a template uses.

## Other template engines (Twig, Blade, …)

The analyzer's core is HTML-based, not Fluid-based: it parses templates as HTML5
fragments and reasons about the element tree. Other engines' control flow
degrades to opaque text exactly like Fluid's `{...}` — verified by probe:

- **Twig**: `<ul><li class="item">` parse as elements; `{% for %}` / `{{ }}`
  become text nodes. Structure intact.
- **Blade**: `<div>` and even components like `<x-card :title>` parse as
  elements; `@foreach` / `{{ }}` become text. Structure intact.

So the *engine-agnostic* half already works on any HTML-producing template:
`analyze` / `similar` (structural clone & near-duplicate detection), the WCAG /
a11y `lint` rules, and the CSS/JS-library `consistency` checks (sliders, icons,
css, js-framework, lightbox, animation, lazyload, maps, video-player, grid,
tooltip, cookie-consent).

What proper multi-engine support would need:

- **File discovery** — `TemplateFinder` only globs `.html` today; add `.twig`,
  `.blade.php`, etc. (configurable extensions).
- **An engine switch** — so the Fluid-only rules/checks don't run (and mislead)
  elsewhere: `image.prefer-fluid` and the five Fluid consistency checks
  (`namespace-style`, `render-style`, `translate-style`, `image-approach`,
  `link-approach`) are Fluid-specific; on other engines they are noise or
  "none found".
- **Per-engine rule/check sets** — the real value-add: e.g. Twig-specific or
  Blade-specific conventions (`{% include %}` vs `{{ include() }}`, `@include`
  vs `<x-component>`), the equivalents of the Fluid style checks.
- **Engine-specific preprocessing** (maybe) — untested edge cases such as
  `{% ... %}` mid-attribute or exotic Blade directives may parse oddly.

Positioning stays Fluid-first (that's the USP); this would be an opt-in
expansion, not a rename. A natural first step is just the extension globbing +
engine switch, which unlocks the HTML/a11y/clone half for Twig and Blade with
little effort.

## Tooling

- Auto-fix (`--fix`) for a subset of findings — generate the Partial + `<f:render>`
  replacement for clones; add missing `alt=""`/`type="button"`. Large; a v2 theme.
- `--exclude-path` also honoured by a per-command CLI (done) and a global config
  (done); consider `.gitignore`-style ignore files.
- SARIF for `analyze`/`similar`/`components` as well as `lint`.

## i18n (`i18n`, since 0.10.0)

- **Sentences with inline markup**: move `Please <a …>click here</a>` as one label
  with HTML (CDATA) and `f:format.raw`, or split it into a label with placeholders
  and a link ViewHelper argument. Needs an escaping story, so v1 only reports it.
- **Literals in Fluid arrays and arguments**: rewrite `aria="{label: 'Search'}"`,
  and inspect strings passed to Partials (`arguments="{label: 'Save'}"`) or
  ViewHelpers (`then: 'Show more'`) — they may end up on the page.
- **Expressions inside a text**: `From {price -> f:format.currency()}` could become
  an argument (`arguments="{0: '{price -> f:format.currency()}'}"`).
- **Language detection** to warn when `--language` does not match the texts.
- **XLIFF 2.0** files.
- The `Source/` scanner gives byte offsets; it is the groundwork for
  `components --fix` below.

## Components (`components`, since 0.9.0)

- `components --fix`: write the drafted component file and replace the
  occurrences with the call (byte offsets now exist in `src/Source/`, built for
  `i18n --fix`; v2 theme like the Partial auto-fix).
- Duplication as runs of sibling nodes (address lines separated by `<br>`), which
  the element-based detectors cannot see.
- Named slots, once Fluid supports more than the default slot.
- Resolve Partial names through TypoScript `partialRootPaths` when a TYPO3
  instance is available (the native `fluidlens:components` command could ask it).
- Wrap long attribute lists in drafts onto several lines (whitespace-safe inside
  the opening tag).
- Similar markup: let the slot also absorb several diverging regions when their
  common ancestor is the root, as an opt-in (today such copies are skipped).
