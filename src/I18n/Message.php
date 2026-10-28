<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * A text ready for a language file: the label with {@code %1$s}-style
 * placeholders where the template had variables, and those variables in order.
 */
final class Message
{
    /**
     * @param list<string> $arguments the variable paths behind %1$s, %2$s, …
     */
    public function __construct(
        public readonly string $label,
        public readonly array $arguments = [],
    ) {
    }

    public function hasArguments(): bool
    {
        return $this->arguments !== [];
    }
}
