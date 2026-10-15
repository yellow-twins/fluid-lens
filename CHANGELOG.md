# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- `components` command: suggests which Partials should become Fluid components
  (Fluid 5 / TYPO3 14). For every Partial rendered from several files it
  proposes a component name and tag, the arguments (from the variables the
  Partial reads, declared `f:argument`s taken over unchanged, implicit
  `{settings.*}` turned into explicit arguments) with inferred types, and prints
  a ready-to-copy component draft plus the call replacing each render call.
  Partials that rely on their caller's context stay Partials with the reason
  (`f:form.*` fields outside their form, sections of the calling template,
  layouts); conversions that need care carry caveats (implicit settings,
  callers passing `{_all}`, callers passing different arguments). Also lists
  the existing components, Partials no scanned template renders and render
  calls that resolve to no scanned file. Text and JSON output; nothing is
  written. `fluidlens:components` in TYPO3.
  Markup copied into several files is suggested too: the copies are lined up
  node by node, differing attribute values, class names and texts become
  arguments (properties of differently named variables collapse into one
  object argument, loop variables are treated as renames), and variables and
  settings every copy reads become arguments as well. Copies that only share
  their shape (less than half of their values identical), consist of Fluid
  control flow only or would need too many arguments are not suggested.
  Overlapping suggestions are reduced to the best one. `--only` selects the
  signals (`partials`, `clones`, `similar`).
  Similar (not identical) markup is aligned as a longest common subsequence:
  an element only some copies have becomes optional behind a boolean argument,
  a region that differs too much becomes the component's `<f:slot />` (each
  call carries its copy's content). When the copies are the entire content of
  several Partials, the suggestion is to merge those Partials, weighted by how
  often they are rendered. A suggestion that looks like an existing component
  points at it. `--min-shared` / `minShared` sets the share of values and texts
  copies must have in common (default 0.45).
- `ParsedTemplate` keeps the raw template source, and `FluidArray` accepts
  numeric keys and offers `parseRaw()` to tell quoted strings from variables.

- Tolerant HTML5-based Fluid template parser producing a framework-agnostic node
  tree, with Fluid-aware handling of self-closing ViewHelpers, dotted ViewHelper
  names and opaque `{expressions}`, plus source line resolution.
- `analyze` command: exact clone detection via a canonical structure hash,
  with subsumption of nested groups and a readable structural preview.
- `similar` command: near-duplicate detection via pq-gram similarity, clustered
  by a divergence score.
- `lint` command: accessibility (WCAG) and best-practice sniffs — missing image
  `alt`, icon-only links and buttons, duplicate ids, unlabelled form controls,
  missing `<html lang>`, positive `tabindex`, tables without headers, unknown
  ARIA roles, skipped heading levels, inline styles and inline SVGs. Criteria
  that need a rendered page are reported as needing a runtime check rather than
  silently passed. Rules can be filtered with `--only` / `--exclude` and listed
  with `--list-rules`. Also covers icon-only buttons, unknown ARIA roles and
  attributes, `aria-hidden` on focusable elements, untitled iframes, autoplaying
  media, empty headings, zoom-blocking viewport meta tags, raw `<img>` in place
  of `<f:image>`, `target="_blank"` without `rel="noopener"`, and broken
  `<picture>` markup (missing `<img>` fallback, `<source>` without `srcset`),
  broken tabs (`role="tab"` without `aria-selected`, `role="tablist"` without a
  tab), broken accordions (`aria-expanded` on a non-interactive element),
  `<label for>` pointing at a missing id (conservative, skips Fluid forms),
  image buttons without alt, alt text that is just a file name, nested
  interactive controls, `<fieldset>` without `<legend>`, non-`<li>` list
  children, empty `<th>`, ARIA roles missing their required state, `<video>`
  without captions, `<marquee>`/`<blink>`, non-descriptive link text, invalid
  `lang`/`dir`/`scope` values, empty `<label>`, invalid boolean ARIA values,
  timed meta refresh, `<summary>` outside `<details>`, and `<abbr>` without a
  title (WCAG 3.1.4, Level AAA). Also covers invalid `autocomplete` autofill
  tokens (1.3.5), `lang`/`xml:lang` disagreement, duplicate `accesskey`,
  `target="_blank"` links that never announce the new tab (3.2.5, Level AAA), and
  `aria-controls`/`aria-labelledby`/`aria-describedby` references and multiple
  navigation landmarks without labels (conservative notices, since the target may
  live in another partial). Statically decidable A/AA/AAA markup criteria are
  covered; the rest keeps its honest "needs runtime check" note.
  `--only` /
  `--exclude` accept a trailing `*` wildcard (e.g. `wcag.*`), and `--list-rules`
  groups rules by prefix and describes each one. Rule descriptions also enrich the
  SARIF output (`shortDescription`). `--fail-on=error|warning|notice|never`
  (also `lint.failOn` in config) controls the severity that fails the run.
- `consistency` command: project-wide checks that fail when competing
  implementations are mixed. Built-in checks: `sliders` (Swiper, Slick, Glide,
  Splide, Owl, Flickity, Keen, Tiny Slider) and `icons` (Font Awesome, Bootstrap
  Icons, Material, Ionicons, Feather, Remix, Boxicons) and `css` (Bootstrap,
  Tailwind, Bulma, Foundation, via distinctive signatures — including regex
  signatures for Tailwind's variant prefixes and colour scale), `js-framework`
  (Alpine, Vue, htmx, Stimulus, Turbo — recognised by their attributes),
  `lightbox` (Fancybox, GLightbox, Magnific Popup, Lightgallery, PhotoSwipe),
  `animation` (Animate.css, WOW.js, AOS), `lazyload` (native `loading` vs
  lazysizes/lozad), `maps` (Leaflet, Mapbox GL, OpenLayers, Google Maps),
  `video-player` (Plyr, Video.js, MediaElement.js, JW Player, Flowplayer),
  `grid` (Isotope, Masonry, Packery, Muuri), `tooltip` (Tippy.js, Foundation,
  hint.css, microtip) and `cookie-consent` (Cookiebot, OneTrust, Osano, Klaro,
  Borlabs). Plus Fluid-specific checks comparing *how* the project writes Fluid:
  `namespace-style` (`<html>` attribute vs inline `{namespace ...}`),
  `render-style` and `translate-style` (tag vs inline ViewHelper syntax),
  `image-approach` (`<f:image>` vs `<f:uri.image>` vs raw `<img>` with a dynamic
  `src`) and `link-approach` (Fluid link ViewHelper vs raw `<a>` with a dynamic
  `href`) — the raw-markup variants only count when the URL is a Fluid
  expression, so static assets and external links are ignored. All selectable
  with `--only` / `--exclude` (wildcards allowed) and listed with
  `--list-checks`. Duplicated markup is covered by `analyze`/`similar`.
- `parse` command: dump a single template's structural node tree.
- Inline suppression via `{# @fluidlint-ignore #}` markers.
- PHPStan-style baseline (`--generate-baseline` / `--baseline`) for adopting the
  tool on existing projects — for duplicate detection, `lint` (matched by rule,
  file and message, ignoring line numbers) and `consistency` (records the
  variants each check accepts today, then fails only when a variant appears that
  the baseline does not list — a third implementation joining an already-mixed
  check, or a previously consistent check becoming mixed).
- Human-readable and `--json` output for every command; `lint --sarif` emits
  SARIF 2.1.0 for GitHub code scanning.
- Project configuration via a `fluid-lens.php` file (`paths`, path `exclude`
  globs, per-command options and default lint rules), with precedence
  command-line > config > default. Scanning commands accept `--config` and fall
  back to the configured `paths`.
- Optional native TYPO3 commands (`fluidlens:analyze`, `fluidlens:similar`).
- CI running phpcs (PSR-12), PHPStan level 7, Psalm level 6 and PHPUnit on
  PHP 8.1–8.4.

## [0.8.1] - 2026-09-20

### Fixed

- Accessibility rules now understand the array arguments that TYPO3's
  tag-based ViewHelpers expand into attributes: `aria="{label: '…'}"`,
  `data="{…}"` and `additionalAttributes="{…}"`. Previously they were opaque,
  so a link labelled with `aria="{label: '…'}"` was reported as having no
  discernible text (`wcag.link-name`) — the same blind spot affected
  `wcag.button-name`, `wcag.form-label` and every `wcag.aria-*` rule. As a
  side effect a typo inside the array, such as `aria="{lable: '…'}"`, is now
  caught by `wcag.aria-attr`. An argument that cannot be read statically
  (`aria="{attributes}"`) silences the name rules instead of guessing.
- A link, button or heading whose only content is `<f:render>` or `<f:cObject>`
  is no longer reported as having no discernible text: what those render cannot
  be known statically, so the conservative assumption is that they produce text.
