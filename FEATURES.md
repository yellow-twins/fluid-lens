# Feature backlog

Ideas not yet built. Grouped by area. Not commitments — a place so good ideas
aren't lost.

## Consistency checks

The `consistency` command runs project-wide checks that fail when competing
implementations are mixed. A new check is one class extending
`ClassSignatureCheck` (or implementing `ConsistencyCheck`) plus a registry entry.
Already shipped: `sliders`, `icons`, `css`.

Candidate checks (detectable by signature classes / attributes):

- **JS interaction frameworks** — Alpine (`x-data`, `x-show`), Vue (`v-if`, `v-for`),
  htmx (`hx-*`), Stimulus (`data-controller`), Turbo, jQuery patterns.
- **Lightbox / gallery** — Fancybox, GLightbox, Magnific Popup, Lightgallery, PhotoSwipe.
- **Animation** — AOS (`data-aos`), Animate.css (`animate__*`), WOW.js (`wow`), GSAP.
- **Lazy-loading strategy** — native `loading="lazy"` vs lazysizes (`data-src`, `lazyload`) vs lozad.
- **Map libraries** — Leaflet (`leaflet-*`), Mapbox (`mapboxgl-*`), Google Maps embeds.
- **Video players** — Plyr (`plyr`), Video.js (`video-js`), native `<video>`.
- **Grid / masonry** — Isotope, Masonry, Packery.
- **Tooltip / popover** — Tippy (`tippy`), Bootstrap tooltips, Popper.
- **Cookie consent** — multiple libraries fighting for the same job.

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
- `autocomplete` attribute valid tokens (1.3.5).
- `lang` on elements with `xml:lang` mismatch.
- Orphaned `aria-controls` / `aria-describedby` targets (careful: cross-partial
  false positives — same reason `label-for` is a conservative notice).

## Tooling

- Auto-fix (`--fix`) for a subset of findings — generate the Partial + `<f:render>`
  replacement for clones; add missing `alt=""`/`type="button"`. Large; a v2 theme.
- `--exclude-path` also honoured by a per-command CLI (done) and a global config
  (done); consider `.gitignore`-style ignore files.
- SARIF for `analyze`/`similar` as well as `lint`.
- A `--baseline` for the `consistency` command.
