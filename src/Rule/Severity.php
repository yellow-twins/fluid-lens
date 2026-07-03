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
}
