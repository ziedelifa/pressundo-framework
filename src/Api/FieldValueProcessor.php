<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

use InvalidArgumentException;
use Pressundo\Framework\Registry\FieldRegistry;
use Pressundo\Framework\Security\SecurityPolicy;

final class FieldValueProcessor
{
    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly OptionsResolver $optionsResolver
    ) {
    }

    /** @param array<string,mixed> $field */
    public function sanitize(array $field, mixed $value): mixed
    {
        $field = $this->withResolvedOptions($field);
        $type = sanitize_key((string) ($field['type'] ?? ''));
        if ($type === '') {
            throw new InvalidArgumentException('Field definition is missing a type.');
        }

        if ($type === 'group') {
            $sanitized = $this->sanitizeGroup($field, $value);
        } elseif ($this->registry->has($type)) {
            $sanitized = $this->registry->get($type)->sanitize($value, $field);
        } else {
            $sanitized = SecurityPolicy::sanitizeUnknown($value);
        }

        $callback = $field['sanitize'] ?? $field['sanitize_callback'] ?? null;
        if (is_callable($callback)) {
            $sanitized = $callback($sanitized, $field);
        }

        if (function_exists('apply_filters')) {
            $sanitized = apply_filters('pressundo_field_sanitized', $sanitized, $value, $field);
            $sanitized = apply_filters('pressundo_field_sanitized_' . $type, $sanitized, $value, $field);
        }

        return $sanitized;
    }

    /** @param array<string,mixed> $field */
    public function validate(array $field, mixed $value): ValidationResult
    {
        $field = $this->withResolvedOptions($field);
        $type = sanitize_key((string) ($field['type'] ?? ''));
        $sanitized = $this->sanitize($field, $value);
        $errors = [];

        if (($field['required'] ?? false) === true) {
            $missing = in_array($type, ['checkbox', 'toggle'], true)
                ? $sanitized !== true
                : $this->isEmpty($sanitized);
            if ($missing) {
                $errors[] = (string) ($field['required_message'] ?? __('This field is required.', 'pressundo-framework'));
            }
        }

        $errors = array_merge($errors, $this->builtinErrors($type, $field, $value, $sanitized));

        if ($type === 'group') {
            $errors = array_merge($errors, $this->groupErrors($field, $value));
        }

        $callback = $field['validate'] ?? $field['validate_callback'] ?? null;
        if (is_callable($callback)) {
            $result = $callback($sanitized, $field);
            if ($result === false) {
                $errors[] = (string) ($field['validation_message'] ?? __('The field value is invalid.', 'pressundo-framework'));
            } elseif (is_string($result) && $result !== '') {
                $errors[] = $result;
            } elseif (is_array($result)) {
                foreach ($result as $error) {
                    if (is_string($error) && $error !== '') {
                        $errors[] = $error;
                    }
                }
            }
        }

        if (function_exists('apply_filters')) {
            /** @var list<string> $errors */
            $errors = (array) apply_filters('pressundo_field_validation_errors', $errors, $sanitized, $field);
        }

        $errors = array_values(array_unique(array_filter(array_map('strval', $errors))));
        return $errors === []
            ? ValidationResult::valid($sanitized)
            : ValidationResult::invalid($sanitized, $errors);
    }

    /** @param array<string,mixed> $field @return list<string> */
    private function builtinErrors(string $type, array $field, mixed $raw, mixed $sanitized): array
    {
        $errors = [];
        $message = (string) ($field['validation_message'] ?? __('The field value is invalid.', 'pressundo-framework'));
        $nonEmptyRaw = ! $this->isEmpty($raw);
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];

        if (in_array($type, ['select', 'radio', 'button_group', 'post_select', 'term_select', 'user_select', 'menu_select', 'sidebar_select', 'template_select'], true) && $nonEmptyRaw && empty($field['multiple'])) {
            $rawValue = sanitize_text_field((string) $raw);
            if (! array_key_exists($rawValue, $options)) {
                $errors[] = $message;
            }
        }

        if ((in_array($type, ['multi_select', 'checkbox_group', 'sortable_select'], true) || (in_array($type, ['post_select', 'term_select', 'user_select', 'menu_select', 'sidebar_select', 'template_select'], true) && ! empty($field['multiple']))) && $nonEmptyRaw) {
            $rawValues = is_array($raw) ? array_values(array_filter($raw, static fn ($v): bool => $v !== '')) : [$raw];
            foreach ($rawValues as $item) {
                if (! array_key_exists(sanitize_text_field((string) $item), $options)) {
                    $errors[] = $message;
                    break;
                }
            }
            if ($type === 'sortable_select' && count($rawValues) !== count(array_unique(array_map('strval', $rawValues)))) {
                $errors[] = __('Sortable selections cannot contain duplicates.', 'pressundo-framework');
            }
            $minSelection = isset($field['min_selection']) ? max(0, (int) $field['min_selection']) : 0;
            $maxSelection = isset($field['max_selection']) ? max(0, (int) $field['max_selection']) : 0;
            $count = is_array($sanitized) ? count($sanitized) : 0;
            if ($minSelection > 0 && $count < $minSelection) {
                /* translators: %d: minimum number of selected items. */
                $errors[] = sprintf(__('Select at least %d item(s).', 'pressundo-framework'), $minSelection);
            }
            if ($maxSelection > 0 && $count > $maxSelection) {
                /* translators: %d: maximum number of selected items. */
                $errors[] = sprintf(__('Select no more than %d item(s).', 'pressundo-framework'), $maxSelection);
            }
        }

        if (in_array($type, ['number', 'range'], true) && $nonEmptyRaw) {
            if (! is_numeric($raw)) {
                $errors[] = $message;
            } else {
                $number = (float) $raw;
                if (isset($field['min']) && is_numeric($field['min']) && $number < (float) $field['min']) {
                    /* translators: %s: minimum allowed numeric value. */
                    $errors[] = sprintf(__('Value must be at least %s.', 'pressundo-framework'), (string) $field['min']);
                }
                if (isset($field['max']) && is_numeric($field['max']) && $number > (float) $field['max']) {
                    /* translators: %s: maximum allowed numeric value. */
                    $errors[] = sprintf(__('Value must be no greater than %s.', 'pressundo-framework'), (string) $field['max']);
                }
                if (isset($field['step']) && is_numeric($field['step']) && (float) $field['step'] > 0) {
                    $step = (float) $field['step'];
                    $base = isset($field['min']) && is_numeric($field['min']) ? (float) $field['min'] : 0.0;
                    $ratio = ($number - $base) / $step;
                    if (abs($ratio - round($ratio)) > 0.0000001) {
                        /* translators: %s: numeric step size. */
                        $errors[] = sprintf(__('Value must respect a step of %s.', 'pressundo-framework'), (string) $field['step']);
                    }
                }
            }
        }

        if ($type === 'email' && $nonEmptyRaw && ! is_email((string) $raw)) {
            $errors[] = $message;
        }
        if ($type === 'url' && $nonEmptyRaw && filter_var((string) $raw, FILTER_VALIDATE_URL) === false) {
            $errors[] = $message;
        }
        if ($type === 'date' && $nonEmptyRaw && $sanitized === '') {
            $errors[] = $message;
        }
        if ($type === 'time' && $nonEmptyRaw && $sanitized === '') {
            $errors[] = $message;
        }
        if ($type === 'color' && $nonEmptyRaw && $sanitized === '') {
            $errors[] = $message;
        }
        if ($type === 'datetime' && $nonEmptyRaw && $sanitized === '') {
            $errors[] = $message;
        }
        if ($type === 'json' && $nonEmptyRaw) {
            json_decode((string) $raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                /* translators: %s: JSON parser error message. */
                $errors[] = sprintf(__('Invalid JSON: %s', 'pressundo-framework'), json_last_error_msg());
            }
        }

        if (is_string($sanitized)) {
            $length = function_exists('mb_strlen') ? mb_strlen($sanitized) : strlen($sanitized);
            if (isset($field['min_length']) && $length < (int) $field['min_length']) {
                /* translators: %d: minimum number of characters. */
                $errors[] = sprintf(__('Use at least %d characters.', 'pressundo-framework'), (int) $field['min_length']);
            }
            if (isset($field['max_length']) && $length > (int) $field['max_length']) {
                /* translators: %d: maximum number of characters. */
                $errors[] = sprintf(__('Use no more than %d characters.', 'pressundo-framework'), (int) $field['max_length']);
            }
            if (! empty($field['pattern']) && @preg_match((string) $field['pattern'], '') !== false && preg_match((string) $field['pattern'], $sanitized) !== 1) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    /** @param array<string,mixed> $field @return list<string> */
    private function groupErrors(array $field, mixed $value): array
    {
        $children = array_values(array_filter((array) ($field['fields'] ?? []), 'is_array'));
        $repeating = ! empty($field['repeating']);
        $errors = [];

        if (! is_array($value)) {
            return $errors;
        }

        $rows = $repeating ? array_values($value) : [$value];
        if ($repeating) {
            $count = count($rows);
            if (isset($field['min_rows']) && $count < (int) $field['min_rows']) {
                /* translators: %d: minimum number of repeater rows. */
                $errors[] = sprintf(__('Add at least %d row(s).', 'pressundo-framework'), (int) $field['min_rows']);
            }
            if (isset($field['max_rows']) && (int) $field['max_rows'] > 0 && $count > (int) $field['max_rows']) {
                /* translators: %d: maximum number of repeater rows. */
                $errors[] = sprintf(__('Use no more than %d row(s).', 'pressundo-framework'), (int) $field['max_rows']);
            }
        }

        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($children as $child) {
                $name = sanitize_key((string) ($child['name'] ?? $child['id'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $childValue = array_key_exists($name, $row) ? $row[$name] : ($child['default'] ?? null);
                $result = $this->validate($child, $childValue);
                foreach ($result->errors() as $error) {
                    $prefix = $repeating ? sprintf('%s[%d].%s', (string) ($field['name'] ?? 'group'), $rowIndex, $name) : sprintf('%s.%s', (string) ($field['name'] ?? 'group'), $name);
                    $errors[] = $prefix . ': ' . $error;
                }
            }
        }
        return $errors;
    }

    /** @param array<string,mixed> $field */
    private function sanitizeGroup(array $field, mixed $value): mixed
    {
        $children = array_values(array_filter((array) ($field['fields'] ?? []), 'is_array'));
        $repeating = ! empty($field['repeating']);
        if (! is_array($value)) {
            return [];
        }
        if (! $repeating) {
            return $this->sanitizeRow($children, $value);
        }
        $rows = [];
        $schemaMax = isset($field['max_rows']) && (int) $field['max_rows'] > 0 ? (int) $field['max_rows'] : PHP_INT_MAX;
        $hardMax = min($schemaMax, SecurityPolicy::maxRepeaterRows());
        foreach ($value as $row) {
            if (count($rows) >= $hardMax) {
                break;
            }
            if (is_array($row)) {
                $rows[] = $this->sanitizeRow($children, $row);
            }
        }
        return $rows;
    }

    /** @param list<array<string,mixed>> $fields @param array<string|int,mixed> $values @return array<string,mixed> */
    private function sanitizeRow(array $fields, array $values): array
    {
        $result = [];
        foreach ($fields as $child) {
            $name = sanitize_key((string) ($child['name'] ?? $child['id'] ?? ''));
            if ($name === '') { continue; }
            $raw = array_key_exists($name, $values) ? $values[$name] : ($child['default'] ?? null);
            $result[$name] = $this->sanitize($child, $raw);
        }
        return $result;
    }

    /** @param array<string,mixed> $field @return array<string,mixed> */
    private function withResolvedOptions(array $field): array
    {
        if (isset($field['options']) || isset($field['options_callback'])) {
            $field['options'] = $this->optionsResolver->resolve($field);
        }
        return $field;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }
}
