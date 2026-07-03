<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

/**
 * How serious a finding is. Errors and warnings fail a run (and therefore CI);
 * notices are advisory and never fail the build.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';

    public function failsBuild(): bool
    {
        return $this !== self::Notice;
    }

    /**
     * A comparable order of seriousness, highest first: error 3, warning 2, notice 1.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Error => 3,
            self::Warning => 2,
            self::Notice => 1,
        };
    }
}
