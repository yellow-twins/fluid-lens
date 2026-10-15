<?php

declare(strict_types=1);

/*
 * Example fluid-lens configuration.
 *
 * Copy this file to `fluid-lens.php` in your project root and adjust it. Every
 * key is optional; anything omitted falls back to the command's default, and any
 * command-line option overrides the value set here.
 */

return [
    // Paths scanned when no path argument is given on the command line.
    'paths' => [
        'packages/',
    ],

    // Glob patterns of files to skip (matched against each file path).
    'exclude' => [
        '*/Tests/*',
    ],

    'lint' => [
        // Rules to skip by default (e.g. the advisory notices). See `lint --list-rules`.
        'exclude' => [
            'style.inline',
            'partial.inline-svg',
        ],
        // Or, to run only a specific set instead, list them here:
        // 'only' => ['wcag.img-alt', 'wcag.button-name'],

        // Severity that makes the run fail: error, warning (default), notice or never.
        // 'failOn' => 'error',

        // Suppress findings recorded in this baseline (see lint --generate-baseline).
        // 'baseline' => 'fluid-lens-lint-baseline.json',
    ],

    'analyze' => [
        'minElements' => 3,
        'minOccurrences' => 2,
        // 'baseline' => 'fluid-lens-baseline.json',
    ],

    'similar' => [
        'threshold' => 0.8,
        'minElements' => 4,
    ],

    'components' => [
        // A candidate needs this many call sites (render calls or copies) ...
        'minOccurrences' => 2,
        // ... spread over this many files; repetition inside one file is a loop, not a component.
        'minFiles' => 2,
        // Minimum size of copied markup.
        'minElements' => 3,
        // More arguments than this flag a suggestion as "too configurable".
        'maxArguments' => 6,
        // Similarity for similar (not identical) markup, 0..1.
        'threshold' => 0.8,
        // Share of values and texts copies must have in common; below it they only share their shape.
        'minShared' => 0.45,
        // Signals to use: partials, clones, similar (default: all).
        // 'only' => ['partials'],
        // Component namespace prefixes besides those detected from xmlns/{namespace}.
        // 'prefixes' => ['my'],
        // Accept the current suggestions and only report new ones (see components --generate-baseline).
        // 'baseline' => 'fluid-lens-components-baseline.json',
    ],

    'consistency' => [
        // Accept the current mix and only fail on new drift (see
        // consistency --generate-baseline).
        // 'baseline' => 'fluid-lens-consistency-baseline.json',
    ],
];
