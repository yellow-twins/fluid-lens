<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

/**
 * One {@code f:render} call, in tag or inline form.
 */
final class RenderCall
{
    /**
     * @param array<string, string>|null $arguments the passed arguments, null when none
     *                                              are passed or they cannot be read
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $partial,
        public readonly ?string $section,
        public readonly ?array $arguments,
        public readonly bool $passesAll,
        public readonly bool $hasArguments,
    ) {
    }

    public function isPartialCall(): bool
    {
        return $this->partial !== null;
    }

    /**
     * A partial name built from variables cannot be resolved statically.
     */
    public function isDynamic(): bool
    {
        return $this->partial !== null && str_contains($this->partial, '{');
    }

    /**
     * Whether the passed argument names are known: the call passes a readable
     * array literal, or nothing at all.
     */
    public function argumentsAreKnown(): bool
    {
        return !$this->passesAll && (!$this->hasArguments || $this->arguments !== null);
    }

    /**
     * @return list<string>
     */
    public function argumentNames(): array
    {
        return array_keys($this->arguments ?? []);
    }
}
