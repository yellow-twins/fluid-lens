# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

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
  title (WCAG 3.1.4, Level AAA). Statically decidable A/AA/AAA markup criteria are
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
  signatures for Tailwind's variant prefixes and colour scale), selectable with
  `--only` / `--exclude` (wildcards allowed) and listed with `--list-checks`.
  Duplicated markup is covered by `analyze`/`similar`.
- `parse` command: dump a single template's structural node tree.
- Inline suppression via `{# @fluidlint-ignore #}` markers.
- PHPStan-style baseline (`--generate-baseline` / `--baseline`) for adopting the
  tool on existing projects — for both duplicate detection and `lint` (the lint
  baseline matches by rule, file and message, ignoring line numbers).
- Human-readable and `--json` output for every command; `lint --sarif` emits
  SARIF 2.1.0 for GitHub code scanning.
- Project configuration via a `fluid-lens.php` file (`paths`, path `exclude`
  globs, per-command options and default lint rules), with precedence
  command-line > config > default. Scanning commands accept `--config` and fall
  back to the configured `paths`.
- Optional native TYPO3 commands (`fluidlens:analyze`, `fluidlens:similar`).
- CI running phpcs (PSR-12), PHPStan level 7, Psalm level 6 and PHPUnit on
  PHP 8.1–8.4.
