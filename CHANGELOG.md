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
- `parse` command: dump a single template's structural node tree.
- Inline suppression via `{# @fluidlint-ignore #}` markers.
- PHPStan-style baseline (`--generate-baseline` / `--baseline`) for adopting the
  tool on existing projects.
- Human-readable and `--json` output for every command.
- Optional native TYPO3 commands (`fluidlens:analyze`, `fluidlens:similar`).
- CI running phpcs (PSR-12), PHPStan level 7, Psalm level 6 and PHPUnit on
  PHP 8.1–8.4.
