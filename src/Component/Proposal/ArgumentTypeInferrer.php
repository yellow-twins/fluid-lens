<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Proposal;

use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Fluid\Expressions;
use YellowTwins\FluidLens\Fluid\VariableUsage;
use YellowTwins\FluidLens\Support\FluidArray;

/**
 * Infers the Fluid type of a proposed argument from how the markup uses it and
 * from the values the callers pass. When neither says enough the type stays
 * {@see ArgumentType::Mixed} instead of guessing a narrower one that could reject
 * a valid value.
 */
final class ArgumentTypeInferrer
{
    private const BOOLEAN_LITERALS = ['true', 'false', '{true}', '{false}', '1', '0'];

    /**
     * @param list<string> $values values seen at call sites or copies
     */
    public function infer(array $values, ?VariableUsage $usage = null): ArgumentType
    {
        if ($usage !== null) {
            $fromUsage = $this->fromUsage($usage);
            if ($fromUsage !== null) {
                return $fromUsage;
            }
        }

        return $this->fromValues($values);
    }

    private function fromUsage(VariableUsage $usage): ?ArgumentType
    {
        return match (true) {
            $usage->hasHint(VariableUsage::HINT_EACH) => ArgumentType::Array,
            $usage->hasHint(VariableUsage::HINT_CONDITION) && !$usage->isAccessedByPath() => ArgumentType::Bool,
            $usage->isAccessedByPath() => ArgumentType::Object,
            default => null,
        };
    }

    /**
     * @param list<string> $values
     */
    private function fromValues(array $values): ArgumentType
    {
        $values = array_values(array_filter($values, static fn (string $value): bool => trim($value) !== ''));
        if ($values === []) {
            return ArgumentType::Mixed;
        }

        if ($this->all($values, self::isBooleanExpression(...))) {
            return ArgumentType::Bool;
        }

        if ($this->all($values, self::isArrayLiteral(...))) {
            return ArgumentType::Array;
        }

        $literals = array_values(array_filter($values, static fn (string $v): bool => !Expressions::isSingle($v)));
        if (count($literals) !== count($values)) {
            // At least one caller passes a variable: its type is not visible here.
            return $literals === [] ? ArgumentType::Mixed : ArgumentType::String;
        }

        return $this->fromLiterals($literals);
    }

    /**
     * @param list<string> $literals
     */
    private function fromLiterals(array $literals): ArgumentType
    {
        if ($this->all($literals, static fn (string $v): bool => preg_match('/^-?\d+$/', trim($v)) === 1)) {
            $distinct = array_unique(array_map('trim', $literals));
            sort($distinct);

            return $distinct === ['0', '1'] ? ArgumentType::Bool : ArgumentType::Int;
        }

        if ($this->all($literals, static fn (string $v): bool => is_numeric(trim($v)))) {
            return ArgumentType::Float;
        }

        if ($this->all($literals, self::isBooleanLiteral(...))) {
            return ArgumentType::Bool;
        }

        return ArgumentType::String;
    }

    private static function isBooleanExpression(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['{true}', '{false}'], true);
    }

    private static function isBooleanLiteral(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::BOOLEAN_LITERALS, true);
    }

    private static function isArrayLiteral(string $value): bool
    {
        return str_starts_with(trim($value), '{') && FluidArray::parse($value) !== null;
    }

    /**
     * @param list<string>           $values
     * @param callable(string): bool $test
     */
    private function all(array $values, callable $test): bool
    {
        foreach ($values as $value) {
            if (!$test($value)) {
                return false;
            }
        }

        return true;
    }
}
