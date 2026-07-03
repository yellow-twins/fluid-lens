<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Config;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves an effective option value with the precedence
 * command-line > configuration file > built-in default.
 *
 * A value counts as coming from the command line only when the option was
 * actually passed, which is what {@see InputInterface::hasParameterOption()}
 * detects — a default returned by {@see InputInterface::getOption()} does not
 * override the configuration file.
 */
final class OptionResolver
{
    public function int(InputInterface $input, string $option, ?int $config, int $default): int
    {
        if ($this->provided($input, $option)) {
            $value = $input->getOption($option);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return $config ?? $default;
    }

    public function float(InputInterface $input, string $option, ?float $config, float $default): float
    {
        if ($this->provided($input, $option)) {
            $value = $input->getOption($option);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return $config ?? $default;
    }

    public function string(InputInterface $input, string $option, ?string $config): ?string
    {
        if ($this->provided($input, $option)) {
            $value = $input->getOption($option);
            if (is_string($value)) {
                return $value;
            }
        }

        return $config;
    }

    /**
     * @param list<string> $config
     *
     * @return list<string>
     */
    public function stringList(InputInterface $input, string $option, array $config): array
    {
        if ($this->provided($input, $option)) {
            return self::parseCsv($input->getOption($option));
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    public static function parseCsv(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $items = array_map('trim', explode(',', $value));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }

    private function provided(InputInterface $input, string $option): bool
    {
        return $input->hasParameterOption('--' . $option, true);
    }
}
