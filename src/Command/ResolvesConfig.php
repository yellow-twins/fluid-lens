<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Config\Config;
use YellowTwins\FluidLens\Config\ConfigLoader;
use YellowTwins\FluidLens\Template\TemplateCollection;

/**
 * Shared configuration handling for the analysis commands: loading the config
 * file, resolving which paths to scan, and reporting skipped files.
 */
trait ResolvesConfig
{
    private function loadConfig(InputInterface $input): Config
    {
        $path = $input->getOption('config');

        return (new ConfigLoader())->load(is_string($path) ? $path : null);
    }

    /**
     * The path from the command line takes precedence; otherwise the configured
     * paths are used.
     *
     * @return list<string>
     */
    private function resolvePaths(InputInterface $input, Config $config): array
    {
        $argument = $input->getArgument('path');
        if (is_string($argument) && $argument !== '') {
            return [$argument];
        }

        return $config->paths;
    }

    private function warnAboutSkipped(SymfonyStyle $io, TemplateCollection $collection): void
    {
        foreach ($collection->skipped as $skip) {
            $io->warning(sprintf('Skipped %s: %s', $skip['file'], $skip['reason']));
        }
    }
}
