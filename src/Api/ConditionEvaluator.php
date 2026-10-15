<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

final class ConditionEvaluator
{
    /** @param array<string,mixed> $field @param array<string,mixed> $values */
    public function visible(array $field, array $values): bool
    {
        $show = $this->normalize($field['show_if'] ?? []);
        $hide = $this->normalize($field['hide_if'] ?? []);
        $relation = strtolower((string) ($field['condition_relation'] ?? 'and')) === 'or' ? 'or' : 'and';

        if ($show !== [] && ! $this->matches($show, $values, $relation)) {
            return false;
        }
        if ($hide !== [] && $this->matches($hide, $values, $relation)) {
            return false;
        }
        return true;
    }

    /** @param mixed $definition @return list<array{field:string,operator:string,value:mixed}> */
    public function normalize(mixed $definition): array
    {
        if (! is_array($definition) || $definition === []) {
            return [];
        }

        if (isset($definition['field'])) {
            return [$this->normalizeRule($definition)];
        }

        $rules = [];
        $isList = array_is_list($definition);
        foreach ($definition as $key => $rule) {
            if ($isList && is_array($rule)) {
                $rules[] = $this->normalizeRule($rule);
                continue;
            }
            if (! $isList && is_string($key)) {
                $rules[] = ['field' => sanitize_key($key), 'operator' => '=', 'value' => $rule];
            }
        }
        return array_values(array_filter($rules, static fn (array $rule): bool => $rule['field'] !== ''));
    }

    /** @param list<array{field:string,operator:string,value:mixed}> $rules @param array<string,mixed> $values */
    private function matches(array $rules, array $values, string $relation): bool
    {
        $results = [];
        foreach ($rules as $rule) {
            $actual = $this->readValue($values, $rule['field']);
            $results[] = $this->compare($actual, $rule['operator'], $rule['value']);
        }
        if ($results === []) {
            return true;
        }
        return $relation === 'or' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    /** @param array<string,mixed> $rule @return array{field:string,operator:string,value:mixed} */
    private function normalizeRule(array $rule): array
    {
        $operator = strtolower(trim((string) ($rule['operator'] ?? $rule['op'] ?? '=')));
        $allowed = ['=', '==', '!=', '<>', 'in', 'not_in', '>', '>=', '<', '<=', 'empty', 'not_empty', 'truthy', 'falsy', 'contains', 'not_contains'];
        if (! in_array($operator, $allowed, true)) {
            $operator = '=';
        }
        return [
            'field' => sanitize_key((string) ($rule['field'] ?? '')),
            'operator' => $operator,
            'value' => $rule['value'] ?? null,
        ];
    }

    /** @param array<string,mixed> $values */
    private function readValue(array $values, string $field): mixed
    {
        if (array_key_exists($field, $values)) {
            return $values[$field];
        }
        $current = $values;
        foreach (explode('.', $field) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=', '==' => $this->looseEqual($actual, $expected),
            '!=', '<>' => ! $this->looseEqual($actual, $expected),
            'in' => in_array($actual, is_array($expected) ? $expected : [$expected], true),
            'not_in' => ! in_array($actual, is_array($expected) ? $expected : [$expected], true),
            '>' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            '>=' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            '<' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            '<=' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'empty' => $this->emptyValue($actual),
            'not_empty' => ! $this->emptyValue($actual),
            'truthy' => (bool) $actual === true,
            'falsy' => (bool) $actual === false,
            'contains' => $this->contains($actual, $expected),
            'not_contains' => ! $this->contains($actual, $expected),
            default => false,
        };
    }

    private function looseEqual(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        if (is_scalar($a) && is_scalar($b)) {
            return (string) $a === (string) $b;
        }
        return $a === $b;
    }

    private function emptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, true);
        }
        return is_scalar($actual) && str_contains((string) $actual, (string) $expected);
    }
}
