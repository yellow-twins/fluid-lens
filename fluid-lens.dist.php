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

    'lint' => [
        // Rules to skip by default (e.g. the advisory notices). See `lint --list-rules`.
        'exclude' => [
            'style.inline',
            'partial.inline-svg',
        ],
        // Or, to run only a specific set instead, list them here:
        // 'only' => ['wcag.img-alt', 'wcag.button-name'],
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
];
