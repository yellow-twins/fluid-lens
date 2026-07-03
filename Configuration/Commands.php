<?php

declare(strict_types=1);

use YellowTwins\FluidLens\Command\AnalyzeCommand;
use YellowTwins\FluidLens\Command\LintCommand;
use YellowTwins\FluidLens\Command\SimilarCommand;
use YellowTwins\FluidLens\Command\SlidersCommand;

// Optional TYPO3 integration.
//
// When fluid-lens is registered as a TYPO3 extension, this file exposes its
// analysers as native commands, so they can be run through the TYPO3 binary:
//
//   vendor/bin/typo3 fluidlens:analyze packages/my_ext/Resources/Private/Templates
//   vendor/bin/typo3 fluidlens:similar packages/my_ext/Resources/Private/Templates
//
// The commands construct their own dependencies, so no service configuration is
// required. In a plain (non-TYPO3) project just use the standalone binary
// `vendor/bin/fluid-lens` instead — this file is then simply ignored.

return [
    'fluidlens:analyze' => ['class' => AnalyzeCommand::class],
    'fluidlens:similar' => ['class' => SimilarCommand::class],
    'fluidlens:lint' => ['class' => LintCommand::class],
    'fluidlens:sliders' => ['class' => SlidersCommand::class],
];
