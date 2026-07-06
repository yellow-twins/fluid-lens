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
- SARIF for `analyze`/`similar` as well as `lint`.
