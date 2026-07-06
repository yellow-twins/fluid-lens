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

## Tooling

- Auto-fix (`--fix`) for a subset of findings — generate the Partial + `<f:render>`
  replacement for clones; add missing `alt=""`/`type="button"`. Large; a v2 theme.
- `--exclude-path` also honoured by a per-command CLI (done) and a global config
  (done); consider `.gitignore`-style ignore files.
- SARIF for `analyze`/`similar` as well as `lint`.
- A `--baseline` for the `consistency` command.
