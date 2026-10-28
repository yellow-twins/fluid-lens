<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Source;

/**
 * A scanned tag: its name, whether it opens, closes or closes itself, and its
 * attributes.
 */
final class TagToken
{
    /**
     * @param list<AttributeToken> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $isClosing,
        public readonly bool $isSelfClosing,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * The name in lowercase. HTML is case-insensitive; Fluid ViewHelper names
     * are compared case-insensitively too, as the rest of fluid-lens does.
     */
    public function lowercaseName(): string
    {
        return strtolower($this->name);
    }

    public function attribute(string $lowercaseName): ?AttributeToken
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->lowercaseName() === $lowercaseName) {
                return $attribute;
            }
        }

        return null;
    }

    public function attributeValue(string $lowercaseName): ?string
    {
        return $this->attribute($lowercaseName)?->value;
    }
}
