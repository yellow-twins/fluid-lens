# fluid-lens

[![Packagist Version](https://img.shields.io/packagist/v/yellow-twins/fluid-lens)](https://packagist.org/packages/yellow-twins/fluid-lens)
[![QA](https://github.com/yellow-twins/fluid-lens/actions/workflows/qa.yml/badge.svg)](https://github.com/yellow-twins/fluid-lens/actions/workflows/qa.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/yellow-twins/fluid-lens)](https://packagist.org/packages/yellow-twins/fluid-lens)
[![License](https://img.shields.io/packagist/l/yellow-twins/fluid-lens)](LICENSE)

> Static analysis for Fluid templates — find markup that should be a Partial, and catch accessibility & best-practice problems, straight from the command line.

`fluid-lens` reads your Fluid templates and *x-rays* them: it looks for duplicated
markup structures that should be extracted into reusable Partials, and (from a later
milestone) flags accessibility and best-practice violations — the kind of review you
would otherwise do by hand, one template at a time.

It runs **standalone** (no TYPO3 instance required) as a Composer tool, and ships an
optional TYPO3 command wrapper.

## Status

**Usable today.** fluid-lens finds exact duplicated structures and near-duplicates
across your templates, and lints them for accessibility (WCAG) and best-practice
problems — with inline suppression and a baseline for adopting it on an existing
project. See the roadmap for what's next.

## Why

Fluid is built around Partials so that markup — images, accordions, tiles, list views,
navigations — lives in *one* place and is reused everywhere. In practice most projects
re-implement the same structures inline across many templates. `fluid-lens` makes that
duplication visible and measurable, like PHPStan or PHP_CodeSniffer do for PHP.

## Requirements

- PHP 8.1+
- ext-dom, ext-json

## Installation

```bash
composer require --dev yellow-twins/fluid-lens
```

Or, for development inside this repository, the package lives in
`packages/fluid-lens/` and is installed on its own:

```bash
cd packages/fluid-lens
composer install
```

## Usage

### Analyse for duplicated structures

Scan a file or a whole directory for markup that repeats and should become a Partial:

```bash
vendor/bin/fluid-lens analyze path/to/Templates/
```

Tune the sensitivity, or emit JSON for CI and tooling:

```bash
vendor/bin/fluid-lens analyze path/to/Templates/ --min-elements=5 --min-occurrences=3
vendor/bin/fluid-lens analyze path/to/Templates/ --json
```

The command exits non-zero when duplicates are found, so it can gate a pipeline.

### Find near-duplicate structures

Find blocks that are *almost* identical — differing by a node or an attribute —
and could share one Partial with the differences passed as arguments:

```bash
vendor/bin/fluid-lens similar path/to/Templates/
vendor/bin/fluid-lens similar path/to/Templates/ --threshold=0.85 --json
```

Similarity is measured with pq-gram distance, a fast approximation of tree edit
distance. Structures are clustered by transitive similarity above the threshold.

### Check project-wide consistency

Over time projects drift: the same slider gets copied around, and different
libraries or icon sets creep in. The `consistency` command runs a set of
project-wide checks and fails when competing implementations are mixed:

```bash
vendor/bin/fluid-lens consistency packages/
vendor/bin/fluid-lens consistency packages/ --only=sliders     # just one check
vendor/bin/fluid-lens consistency --list-checks
```

Built-in checks (selectable with `--only` / `--exclude`, wildcards allowed):

| Check | Detects |
|-------|---------|
| `sliders` | Slider/carousel libraries (Swiper, Slick, Glide, Splide, Owl, Flickity, Keen, Tiny Slider) |
| `icons`   | Icon sets (Font Awesome, Bootstrap Icons, Material, Ionicons, Feather, Remix, Boxicons) |
| `css`     | CSS frameworks (Bootstrap, Tailwind, Bulma, Foundation) via distinctive signatures |

Duplicated *markup* (e.g. the same slider copied into five templates) is caught
by `analyze` and `similar` — extract it into one shared Partial.

### Check accessibility (WCAG) and best practices

Scan templates for accessibility and best-practice problems in seconds, instead
of opening every page in a browser:

```bash
vendor/bin/fluid-lens lint path/to/Templates/
vendor/bin/fluid-lens lint path/to/Templates/ --json
```

By default the command exits non-zero on any error or warning (notices are
advisory). Adjust the gate with `--fail-on=error|warning|notice|never` — for
example fail only on errors while you adopt it:

```bash
vendor/bin/fluid-lens lint packages/ --fail-on=error
```

What it checks statically:

| Rule | Severity | WCAG |
|------|----------|------|
| `wcag.img-alt` — image without an `alt` attribute | error | 1.1.1 (A) |
| `wcag.link-name` — link with no discernible text (icon-only) | error | 2.4.4 (A) |
| `wcag.button-name` — button with no discernible text (icon-only) | error | 4.1.2 (A) |
| `wcag.duplicate-id` — duplicate `id` in one document | error | 4.1.1 (A) |
| `wcag.form-label` — control with no way to be labelled | warning | 4.1.2 (A) |
| `wcag.label-for` — `<label for>` with no matching id (skips Fluid forms) | notice | 1.3.1 (A) |
| `wcag.html-lang` — `<html>` without `lang` | warning | 3.1.1 (A) |
| `wcag.positive-tabindex` — `tabindex` greater than 0 | warning | 2.4.3 (A) |
| `wcag.table-header` — data table without `<th>` | warning | 1.3.1 (A) |
| `wcag.empty-heading` — heading with no text | warning | 1.3.1 (A) |
| `wcag.meta-viewport` — viewport meta tag that blocks zoom | warning | 1.4.4 (AA) |
| `wcag.aria-role` — unknown WAI-ARIA `role` value | warning | 4.1.2 (A) |
| `wcag.aria-attr` — unknown `aria-*` attribute (typo) | warning | 4.1.2 (A) |
| `wcag.aria-hidden-focusable` — `aria-hidden` on a focusable element | warning | 4.1.2 (A) |
| `wcag.aria-expanded-role` — `aria-expanded` on a non-interactive element (broken accordion) | warning | 4.1.2 (A) |
| `wcag.tab-selected` — `role="tab"` without `aria-selected` | warning | 4.1.2 (A) |
| `wcag.tablist-tab` — `role="tablist"` without any `role="tab"` | warning | 1.3.1 (A) |
| `wcag.iframe-title` — `<iframe>` without a `title` | warning | 4.1.2 (A) |
| `wcag.media-autoplay` — audio/unmuted video that autoplays sound | warning | 1.4.2 (A) |
| `wcag.heading-order` — heading levels skipped | warning | 1.3.1 (A) |
| `markup.picture-img` — `<picture>` without an `<img>` fallback | warning | — |
| `markup.source-srcset` — `<source>` in `<picture>` without `srcset` | warning | — |
| `style.inline` — inline `style` attribute | notice | — |
| `partial.inline-svg` — inline `<svg>` to extract into an Icon partial | notice | — |
| `image.prefer-fluid` — raw `<img>` instead of `<f:image>` | notice | — |
| `link.target-blank-rel` — `target="_blank"` without `rel="noopener"` | notice | — |

Pick which rules run with `--only` / `--exclude`, or see them all (grouped) with
`--list-rules`. A trailing `*` matches a prefix:

```bash
vendor/bin/fluid-lens lint --list-rules
vendor/bin/fluid-lens lint path/ --only=wcag.*                 # accessibility only
vendor/bin/fluid-lens lint path/ --exclude=style.*,partial.*   # skip advisory notices
vendor/bin/fluid-lens lint path/ --only=wcag.img-alt,wcag.button-name
```

**Honest by design:** criteria that genuinely need a rendered page — colour
contrast, runtime focus order, reflow — are *not* silently passed. The report
states plainly that they must be verified with a runtime tool (axe, Lighthouse).
A static pass is a fast first line of defence, not a replacement for those.

### Suppress a block inline

Mark a block that should intentionally stay inline with a comment on the line
before it — it is then excluded from every analysis:

```html
{# @fluidlint-ignore this one really is a one-off #}
<div class="special-case">…</div>
```

### Adopt on an existing project with a baseline

Record all current duplication once, then only see *new* duplication from then on:

```bash
# Freeze what exists today
vendor/bin/fluid-lens analyze path/to/Templates/ --generate-baseline

# Later runs report only duplication that is new or has grown
vendor/bin/fluid-lens analyze path/to/Templates/ --baseline=fluid-lens-baseline.json
```

`lint` has the same baseline (by rule, file and message, ignoring line numbers) —
freeze today's accessibility debt and only fail on new findings:

```bash
vendor/bin/fluid-lens lint path/to/Templates/ --generate-baseline
vendor/bin/fluid-lens lint path/to/Templates/ --baseline=fluid-lens-lint-baseline.json
```

### Parse a single template

Dump the structural tree the analyzer sees — handy for understanding a finding:

```bash
vendor/bin/fluid-lens parse path/to/Template.html
vendor/bin/fluid-lens parse path/to/Template.html --json
```

### Running through Composer

You don't have to call the binary directly. In a project that requires
fluid-lens, add a Composer script referencing the command by name — Composer puts
`vendor/bin` on the path for scripts:

```json
{
    "scripts": {
        "lint:fluid": "fluid-lens analyze packages/",
        "lint:fluid-similar": "fluid-lens similar packages/"
    }
}
```

```bash
composer lint:fluid
composer exec fluid-lens -- analyze packages/   # ad-hoc, without a script
```

This repository itself ships `composer analyze`, `composer similar` and
`composer lint` shortcuts (see `composer.json`), e.g. `composer analyze -- path/`.

## Configuration

Instead of repeating options on every run, drop a `fluid-lens.php` in your project
root (auto-discovered, or point at it with `--config`). Command-line options always
win over the file, which in turn wins over the built-in defaults.

```php
<?php // fluid-lens.php

return [
    'paths'   => ['packages/'],                       // scanned when no path is given
    'exclude' => ['*/Tests/*'],                       // glob patterns of files to skip
    'lint'    => ['exclude' => ['style.inline']],     // or 'only' => [...], 'failOn' => 'error'
    'analyze' => ['minElements' => 3, 'minOccurrences' => 2],
    'similar' => ['threshold' => 0.85, 'minElements' => 4],
];
```

With that in place, `fluid-lens lint` (no arguments) scans the configured paths with
the configured rules. A ready-to-copy [`fluid-lens.dist.php`](fluid-lens.dist.php) ships
with the package.

## Command reference

All scanning commands accept `--config`, skip files matching `--exclude-path`
(comma-separated globs, adding to the configured `exclude`), and, when no path is
given, fall back to the configured `paths`.

| Command   | Purpose                                   | Key options |
|-----------|-------------------------------------------|-------------|
| `analyze` | Find exact duplicated structures          | `--min-elements`, `--min-occurrences`, `--baseline`, `--generate-baseline`, `--json` |
| `similar` | Find near-duplicate structures            | `--threshold`, `--min-elements`, `--json` |
| `lint`    | Check accessibility (WCAG) & best practices | `--only`, `--exclude`, `--fail-on`, `--baseline`, `--generate-baseline`, `--list-rules`, `--json`, `--sarif` |
| `consistency` | Flag mixed slider/icon/other implementations | `--only`, `--exclude`, `--list-checks`, `--json` |
| `parse`   | Dump one template's structural node tree  | `--json` |

### Exit codes

- `0` — no findings (or all findings covered by the baseline)
- `1` — findings were reported, or the path contained no templates

This lets any command gate a CI pipeline: a clean run passes, new duplication fails.

## Using it in TYPO3

The standalone binary works inside any TYPO3 project out of the box, and native
`vendor/bin/typo3 fluidlens:*` commands are available as an option. See
[docs/typo3.md](docs/typo3.md).

## How it works

Fluid is not valid XML, and Fluid itself only parses ViewHelpers — the surrounding
HTML is opaque text to it. To reason about *structure*, `fluid-lens` parses templates
with a tolerant HTML5 parser and:

- keeps Fluid ViewHelper tags (`<f:image>`, `<f:render>`, …) as ordinary elements,
- treats `{expressions}` as opaque text,
- normalises Fluid's XML-style self-closing tags (`<f:image ... />`) so they don't
  wrongly swallow their following siblings the way an HTML5 parser would.

The result is a small, framework-agnostic node tree (`src/Parser/Node.php`) that later
stages fingerprint and compare.

## Roadmap

**Milestone 1 — Parser foundation** ✅
Parse a template into a node tree; `parse` command with text and JSON output.

**Milestone 2 — Exact clones** ✅ *(current)*
Canonical structure hash (ignoring class values, text and variables) groups
identical structures across templates; the `analyze` command reports them with a
structural preview, suppressing groups fully contained in a larger one.

**Milestone 3 — Near-duplicates** ✅ *(current)*
pq-gram similarity catches structures that differ only slightly, clustered by a
divergence score; the `similar` command reports each cluster of variants.

**Milestone 4 — Ignores & baseline** ✅ *(current)*
`{# @fluidlint-ignore #}` inline suppression and a PHPStan-style baseline
(`--generate-baseline` / `--baseline`) for adopting the tool on existing projects.

**Milestone 5 — TYPO3 command wrapper + docs** ✅
Native `fluidlens:*` TYPO3 commands (`Configuration/Commands.php`) and full docs.

**Sniffs — accessibility & best practices** ✅
The `lint` command ships a WCAG markup module (missing `alt`, icon-only links,
duplicate ids, unlabelled controls, heading jumps, tables without headers, …) plus
best-practice sniffs, with criteria that need a rendered page reported as
*needs runtime check* rather than silently passed.

**Next — more sniffs**: accordions/tiles/navigation component patterns, `<picture>`
best practice, expanded ARIA validation.

**Later — Auto-fix**: generate the suggested Partial and the `<f:render>` replacement.

## Continuous integration

Every command exits non-zero on findings, so it gates a pipeline out of the box:

```bash
vendor/bin/fluid-lens analyze packages/ --baseline=fluid-lens-baseline.json
vendor/bin/fluid-lens lint packages/ --exclude=style.inline,partial.inline-svg
```

`lint --sarif` emits SARIF 2.1.0, so accessibility findings show up inline on pull
requests via GitHub code scanning:

```yaml
- name: Fluid accessibility lint
  run: vendor/bin/fluid-lens lint packages/ --sarif > fluid-lens.sarif || true
- uses: github/codeql-action/upload-sarif@v3
  with:
    sarif_file: fluid-lens.sarif
```

## Quality gates

`fluid-lens` holds itself to the standard it enforces:

```bash
composer qa      # phpcs (PSR-12) + PHPStan (level 7) + Psalm (level 6) + PHPUnit
```

## License

GPL-2.0-or-later
