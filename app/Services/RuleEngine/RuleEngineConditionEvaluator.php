<?php

namespace App\Services\RuleEngine;

use InvalidArgumentException;

/**
 * Evaluates rule_engine_rules.condition JSON documents against a context
 * array, per docs/08-Knowledge-Base.md §3. Never eval()'d - this is a
 * constrained interpreter over a fixed operator set (see 04-Architecture.md
 * §8 "Injection" row).
 */
class RuleEngineConditionEvaluator
{
    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $condition, array $context): bool
    {
        if ($condition === []) {
            return true;
        }

        foreach ($condition as $key => $value) {
            $result = match ($key) {
                'and' => collect($value)->every(fn ($sub) => $this->evaluate($sub, $context)),
                'or' => collect($value)->contains(fn ($sub) => $this->evaluate($sub, $context)),
                'not' => ! $this->evaluate($value, $context),
                default => $this->evaluateField($key, $value, $context),
            };

            if (! $result) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $operators
     * @param  array<string, mixed>  $context
     */
    private function evaluateField(string $field, array $operators, array $context): bool
    {
        $actual = data_get($context, $field);

        if ($actual === null) {
            return false;
        }

        foreach ($operators as $operator => $expected) {
            if (! $this->compare($operator, $actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function compare(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '==' => $actual == $expected,
            'in' => is_array($actual)
                ? count(array_intersect($actual, (array) $expected)) > 0
                : in_array($actual, (array) $expected, true),
            default => throw new InvalidArgumentException("Unknown rule condition operator: {$operator}"),
        };
    }
}
