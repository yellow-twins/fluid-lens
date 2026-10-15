<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * One proposed {@code <f:argument>} of a component.
 */
final class ArgumentProposal
{
    /**
     * @param list<string> $observedValues values seen at the call sites or copies, for the reader
     * @param string|null  $declaredType   the type exactly as an existing f:argument declares it
     */
    public function __construct(
        public readonly string $name,
        public readonly ArgumentType $type,
        public readonly bool $optional,
        public readonly ?string $default,
        public readonly ArgumentOrigin $origin,
        public readonly array $observedValues = [],
        public readonly string $description = '',
        public readonly ?string $declaredType = null,
    ) {
    }

    /**
     * The type as written in the draft: an existing declaration wins, so class
     * names and spellings such as "integer" survive unchanged.
     */
    public function typeName(): string
    {
        return $this->declaredType ?? $this->type->value;
    }
}
