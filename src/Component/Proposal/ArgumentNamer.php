<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Proposal;

/**
 * Names proposed arguments and keeps the names unique within one component.
 */
final class ArgumentNamer
{
    /**
     * Names Fluid reserves.
     */
    private const RESERVED = ['_all'];

    /** @var array<string, true> */
    private array $taken = [];

    /**
     * Claims a name, adding the previous path segment (and then a number) when it
     * is already in use: {@code settings.listPid.merch} becomes {@code merch}, or
     * {@code listPidMerch} if {@code merch} is taken.
     */
    public function forPath(string $path): string
    {
        $segments = array_values(array_filter(
            explode('.', $path),
            static fn (string $segment): bool => $segment !== '' && $segment !== 'settings',
        ));
        if ($segments === []) {
            return $this->claim('value');
        }

        $name = '';
        while ($segments !== []) {
            $segment = (string) array_pop($segments);
            $name = $name === '' ? lcfirst(self::camel($segment)) : lcfirst(self::camel($segment)) . ucfirst($name);
            if (!$this->isTaken($name)) {
                return $this->claim($name);
            }
        }

        return $this->claim($name);
    }

    /**
     * Claims the name as given, or with a number appended when it is taken.
     */
    public function claim(string $name): string
    {
        $base = $name === '' ? 'value' : $name;
        $candidate = $base;
        for ($suffix = 2; $this->isTaken($candidate); $suffix++) {
            $candidate = $base . $suffix;
        }

        $this->taken[$candidate] = true;

        return $candidate;
    }

    public function isTaken(string $name): bool
    {
        return isset($this->taken[$name]) || in_array($name, self::RESERVED, true);
    }

    private static function camel(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $value) ?: [];

        return lcfirst(implode('', array_map(static fn (string $word): string => ucfirst($word), $words)));
    }
}
