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

Fluid-specific consistency (needs ViewHelper/tag inspection, not just classes):

- **Namespace declaration style** — `xmlns:f=...` vs `{namespace f=...}` mixed.
- **Partial render style** — `<f:render partial>` vs inline `{f:render(partial:...)}`.
- **Translation approach** — `<f:translate>` vs inline `{f:translate()}` vs raw `LLL:`.
- **Image approach** — `<f:image>` vs `<f:uri.image>`+`<img>` vs raw `<img>`.
- **Link approach** — `<f:link.typolink>` vs `<f:link.page>` vs raw `<a href>`.
- **Icon rendering method** — inline `<svg>` vs icon font vs `<img>` (method, not set).

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
