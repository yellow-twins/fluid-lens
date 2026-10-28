<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Source;

/**
 * One attribute of a scanned tag, with the byte range of its value so the value
 * can be replaced in the source.
 */
final class AttributeToken
{
    /**
     * @param string      $name       the name as written
     * @param int         $valueStart offset of the first byte of the value, inside the quotes
     * @param int         $valueEnd   offset just past the value
     * @param string|null $quote      the quote character, or null for an unquoted or empty attribute
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly int $valueStart,
        public readonly int $valueEnd,
        public readonly ?string $quote,
    ) {
    }

    public function lowercaseName(): string
    {
        return strtolower($this->name);
    }
}
