<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

/**
 * The ViewHelper attributes that introduce a local variable, such as the "as"
 * of f:for. Their value is a name, not an expression.
 */
final class Bindings
{
    /**
     * @var array<string, list<string>> lowercased ViewHelper => binding attributes
     */
    private const ATTRIBUTES = [
        'f:for' => ['as', 'key', 'iteration'],
        'f:groupedfor' => ['as', 'groupkey'],
        'f:variable' => ['name'],
        'f:cycle' => ['as'],
        'f:form.validationresults' => ['as'],
    ];

    /**
     * @return list<string>
     */
    public static function attributesOf(string $element): array
    {
        return self::ATTRIBUTES[$element] ?? [];
    }

    public static function isBinding(string $element, string $attribute): bool
    {
        return in_array($attribute, self::attributesOf($element), true);
    }
}
