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
  of `<f:image>`, and `target="_blank"` without `rel="noopener"`.
- `parse` command: dump a single template's structural node tree.
- Inline suppression via `{# @fluidlint-ignore #}` markers.
- PHPStan-style baseline (`--generate-baseline` / `--baseline`) for adopting the
  tool on existing projects.
- Human-readable and `--json` output for every command; `lint --sarif` emits
  SARIF 2.1.0 for GitHub code scanning.
- Project configuration via a `fluid-lens.php` file (`paths`, per-command
  options and default lint rules), with precedence command-line > config > default.
  Scanning commands accept `--config` and fall back to the configured `paths`.
- Optional native TYPO3 commands (`fluidlens:analyze`, `fluidlens:similar`).
- CI running phpcs (PSR-12), PHPStan level 7, Psalm level 6 and PHPUnit on
  PHP 8.1–8.4.
