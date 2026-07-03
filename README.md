# fluid-lens

> Static analysis for Fluid templates — find markup that should be a Partial, and catch accessibility & best-practice problems, straight from the command line.

`fluid-lens` reads your Fluid templates and *x-rays* them: it looks for duplicated
markup structures that should be extracted into reusable Partials, and (from a later
milestone) flags accessibility and best-practice violations — the kind of review you
would otherwise do by hand, one template at a time.

It runs **standalone** (no TYPO3 instance required) as a Composer tool, and ships an
optional TYPO3 command wrapper.

## Status

**Clone detection is complete and usable.** fluid-lens finds both exact duplicated
structures and near-duplicates across your templates, with inline suppression and a
baseline for adopting it on an existing project. Accessibility/best-practice *sniffs*
(including a WCAG module) are the next layer — see the roadmap.

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

### Parse a single template

Dump the structural tree the analyzer sees — handy for understanding a finding:

```bash
vendor/bin/fluid-lens parse path/to/Template.html
vendor/bin/fluid-lens parse path/to/Template.html --json
```

## Command reference

| Command   | Purpose                                   | Key options |
|-----------|-------------------------------------------|-------------|
| `analyze` | Find exact duplicated structures          | `--min-elements`, `--min-occurrences`, `--baseline`, `--generate-baseline`, `--json` |
| `similar` | Find near-duplicate structures            | `--threshold`, `--min-elements`, `--json` |
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

**Next — Sniffs** (a dedicated, must-have layer):
opinionated rules for images/`<picture>`, inline SVG, accordions/tiles/navigations,
and a **WCAG (up to AAA) accessibility module** that statically flags markup-level
violations (missing `alt`, unlabelled controls, heading jumps, invalid ARIA, …).
Criteria that cannot be decided statically (colour contrast, runtime focus order) are
reported as *needs runtime check* rather than silently passed.

**Later — Auto-fix**: generate the suggested Partial and the `<f:render>` replacement.

## Quality gates

`fluid-lens` holds itself to the standard it enforces:

```bash
composer qa      # phpcs (PSR-12) + PHPStan (level 7) + Psalm (level 6) + PHPUnit
```

## License

GPL-2.0-or-later
