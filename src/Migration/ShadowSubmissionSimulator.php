<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

use Pressundo\Framework\Api\FieldValueProcessor;

final class ShadowSubmissionSimulator
{
    public function __construct(private readonly ?FieldValueProcessor $modernProcessor = null) {}
    /**
     * Simulate an edited modern form submission against sampled legacy data.
     * No WordPress write API is called by this class.
     *
     * @param array<string,mixed> $configuration
     * @param array<string,mixed> $submitted
     * @return array<string,mixed>
     */
    public function simulate(array $configuration, array $submitted): array
    {
        if (empty($configuration['resolved']) || ! is_array($configuration['schema'] ?? null)) {
            return $this->unavailable('The normalized schema is not safely resolved.');
        }

        $shadow = (array) ($configuration['shadow_data'] ?? []);
        $legacy = (array) ($shadow['sample_values'] ?? []);
        if ((int) ($shadow['samples'] ?? 0) < 1) {
            return $this->unavailable('No sampled legacy value is available for shadow submission testing.');
        }

        $result = [
            'status' => 'simulated',
            'submitted_fields' => 0,
            'accepted_fields' => 0,
            'changed_fields' => 0,
            'locked_fields' => 0,
            'rejected_fields' => 0,
            'ignored_input_keys' => 0,
            'changes' => [],
            'locked' => [],
            'rejected' => [],
            'ignored' => [],
            'legacy_hash' => $this->fingerprint($legacy),
            'shadow_hash' => '',
            'payload_changed' => false,
            'baseline_gate_passed' => ! empty($configuration['shadow_save']['write_gate_passed']),
            'submission_gate_passed' => false,
            'write_performed' => false,
            'message' => 'Submitted values were sanitized and serialized into a legacy-compatible shadow payload. No database write was performed.',
            'preserve_unchanged' => ! empty($configuration['compatibility_runtime']['preserve_unchanged']),
        ];

        $payload = $legacy;
        $known = [];
        foreach ($this->schemaFields((array) $configuration['schema']) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $known[] = $name;
            $legacyValue = array_key_exists($name, $legacy) ? $legacy[$name] : ($field['default'] ?? null);
            $hasSubmitted = array_key_exists($name, $submitted);
            $submittedValue = $hasSubmitted ? $submitted[$name] : null;
            $payload[$name] = $this->simulateField($field, $legacyValue, $hasSubmitted, $submittedValue, $result, $name);
        }

        foreach (array_keys($submitted) as $key) {
            $key = (string) $key;
            if (! in_array($key, $known, true)) {
                ++$result['ignored_input_keys'];
                $result['ignored'][] = $key . ': submitted key is not present in the normalized schema and was ignored.';
            }
        }

        $result['payload'] = $payload;
        // Keep the modern submitted form state separate from the legacy payload.
        // The payload intentionally strips repeater bookkeeping keys, while the
        // form state must preserve them so a following controlled-write request
        // can safely re-identify existing/new/reordered rows.
        $result['form_values'] = $submitted;
        $result['shadow_hash'] = $this->fingerprint($payload);
        $result['payload_changed'] = $result['legacy_hash'] !== $result['shadow_hash'];
        $result['submission_gate_passed'] = ! empty($result['baseline_gate_passed'])
            && (int) $result['rejected_fields'] === 0
            && (int) $result['ignored_input_keys'] === 0;

        if (! $result['baseline_gate_passed']) {
            $result['status'] = 'blocked';
        } elseif ((int) $result['rejected_fields'] > 0 || (int) $result['ignored_input_keys'] > 0) {
            $result['status'] = 'rejected';
        } else {
            $result['status'] = 'pass';
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $result
     */
    private function simulateField(array $field, mixed $legacyValue, bool $hasSubmitted, mixed $submittedValue, array &$result, string $path): mixed
    {
        $status = (string) ($field['migration']['status'] ?? 'automatic');
        $type = (string) ($field['type'] ?? 'text');

        if ($status !== 'automatic' || $type === 'legacy_unknown' || in_array($type, ['html', 'notice'], true)) {
            ++$result['locked_fields'];
            $result['locked'][] = sprintf('%s: %s field is locked and preserved from legacy storage.', $path, $status);
            return $legacyValue;
        }

        if ($type === 'group') {
            if (! $hasSubmitted) {
                return $legacyValue;
            }
            if (! is_array($submittedValue)) {
                ++$result['rejected_fields'];
                $result['rejected'][] = $path . ': submitted group must be an array.';
                return $legacyValue;
            }
            return $this->simulateGroup($field, $legacyValue, $submittedValue, $result, $path);
        }

        ++$result['submitted_fields'];
        if (! $hasSubmitted) {
            // Compatibility runtime forms must never erase an existing legacy value
            // merely because a field is not represented by the modern renderer.
            if (! empty($result['preserve_unchanged'])) {
                return $legacyValue;
            }

            // Strict shadow diagnostics still require every editable scalar field.
            ++$result['rejected_fields'];
            $result['rejected'][] = $path . ': expected editable field was missing from the submission.';
            return $legacyValue;
        }

        // In compatibility-runtime mode, unchanged legacy values are preserved
        // byte-for-byte before current-schema validation. This is important for
        // dynamic selects whose available choices may have changed since the value
        // was originally saved. An unrelated field edit must not invalidate them.
        if (! empty($result['preserve_unchanged']) && $this->valuesEquivalent($submittedValue, $legacyValue)) {
            ++$result['accepted_fields'];
            return $legacyValue;
        }

        $sanitized = $this->sanitize($field, $submittedValue);
        if (! $sanitized['valid']) {
            ++$result['rejected_fields'];
            $result['rejected'][] = $path . ': ' . (string) $sanitized['message'];
            return $legacyValue;
        }

        ++$result['accepted_fields'];
        $legacyShape = $this->toLegacyShape($sanitized['value'], $legacyValue, $field);
        if ($legacyShape !== $legacyValue) {
            ++$result['changed_fields'];
            $result['changes'][] = [
                'path' => $path,
                'before' => $this->summarize($legacyValue),
                'after' => $this->summarize($legacyShape),
            ];
        }
        return $legacyShape;
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $result
     */
    private function simulateGroup(array $field, mixed $legacyValue, array $submittedValue, array &$result, string $path): mixed
    {
        $children = (array) ($field['fields'] ?? []);
        $repeating = ! empty($field['repeating']);

        if ($repeating) {
            if (! is_array($legacyValue)) {
                ++$result['rejected_fields'];
                $result['rejected'][] = $path . ': legacy repeater is not an array.';
                return $legacyValue;
            }

            $legacyRows = array_values($legacyValue);
            unset($submittedValue['__pressundo_repeater_present']);
            $submittedRows = array_values($submittedValue);
            $migration = is_array($field['migration'] ?? null) ? $field['migration'] : [];
            $structuralFlag = array_key_exists('structural_editable', $migration) ? ! empty($migration['structural_editable']) : true;
            $structuralEditable = $structuralFlag && $this->repeaterRowsMatchSchema($legacyRows, $children);

            if (! $structuralEditable && count($submittedRows) !== count($legacyRows)) {
                ++$result['rejected_fields'];
                $result['rejected'][] = $path . ': repeater structure is locked because not every nested legacy value is safely represented by the normalized schema.';
                return $legacyValue;
            }

            $rows = [];
            $usedLegacyKeys = [];
            $submittedOrder = [];
            foreach ($submittedRows as $index => $submittedRow) {
                if (! is_array($submittedRow)) {
                    ++$result['rejected_fields'];
                    $result['rejected'][] = sprintf('%s[%s]: invalid repeater row shape.', $path, (string) $index);
                    continue;
                }

                $rowKey = sanitize_text_field((string) ($submittedRow['__pressundo_row_key'] ?? ''));
                unset($submittedRow['__pressundo_row_key']);
                $prototype = [];
                $resolvedLegacyKey = null;

                if ($rowKey !== '' && ctype_digit($rowKey)) {
                    $candidate = (int) $rowKey;
                    if (array_key_exists($candidate, $legacyRows) && ! in_array($candidate, $usedLegacyKeys, true)) {
                        $prototype = is_array($legacyRows[$candidate]) ? $legacyRows[$candidate] : [];
                        $resolvedLegacyKey = $candidate;
                        $usedLegacyKeys[] = $candidate;
                    } else {
                        ++$result['rejected_fields'];
                        $result['rejected'][] = sprintf('%s[%s]: invalid or duplicated legacy row identity.', $path, (string) $index);
                        continue;
                    }
                } elseif ($rowKey === '' && array_key_exists($index, $legacyRows) && ! $structuralEditable) {
                    // Backward-compatible submission produced by an older renderer.
                    $prototype = is_array($legacyRows[$index]) ? $legacyRows[$index] : [];
                    $resolvedLegacyKey = $index;
                    $usedLegacyKeys[] = $index;
                } elseif (! $structuralEditable) {
                    ++$result['rejected_fields'];
                    $result['rejected'][] = sprintf('%s[%s]: new/reordered rows are not allowed for this repeater.', $path, (string) $index);
                    continue;
                }

                $submittedOrder[] = $resolvedLegacyKey === null ? 'new' : (string) $resolvedLegacyKey;
                $rows[] = $this->simulateChildren($children, $prototype, $submittedRow, $result, sprintf('%s[%s]', $path, (string) $index));
            }

            if ($structuralEditable) {
                $originalOrder = array_map('strval', array_keys($legacyRows));
                if (count($rows) !== count($legacyRows) || $submittedOrder !== $originalOrder) {
                    ++$result['changed_fields'];
                    $result['changes'][] = [
                        'path' => $path . '.__rows',
                        'before' => sprintf('%d row(s), order %s', count($legacyRows), implode(',', $originalOrder)),
                        'after' => sprintf('%d row(s), order %s', count($rows), implode(',', $submittedOrder)),
                    ];
                }
            }

            return $rows;
        }

        if (! is_array($legacyValue)) {
            $legacyValue = [];
        }
        return $this->simulateChildren($children, $legacyValue, $submittedValue, $result, $path);
    }

    /** @param array<mixed> $children @param array<string,mixed> $legacy @param array<string,mixed> $submitted @param array<string,mixed> $result @return array<string,mixed> */
    private function simulateChildren(array $children, array $legacy, array $submitted, array &$result, string $path): array
    {
        $payload = $legacy;
        $known = [];
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }
            $name = (string) ($child['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $known[] = $name;
            $legacyValue = array_key_exists($name, $legacy) ? $legacy[$name] : ($child['default'] ?? null);
            $hasSubmitted = array_key_exists($name, $submitted);
            $payload[$name] = $this->simulateField($child, $legacyValue, $hasSubmitted, $hasSubmitted ? $submitted[$name] : null, $result, $path . '.' . $name);
        }

        foreach (array_keys($submitted) as $key) {
            $key = (string) $key;
            if (! in_array($key, $known, true)) {
                ++$result['ignored_input_keys'];
                $result['ignored'][] = $path . '.' . $key . ': unknown submitted key was ignored.';
            }
        }

        return $payload;
    }

    /** @param array<string,mixed> $field @return array{valid:bool,value:mixed,message:string} */
    private function sanitize(array $field, mixed $value): array
    {
        // Fields authored with the modern Pressundo API use the canonical v1
        // processor even when they are appended to a legacy VP_Option page.
        if (! array_key_exists('legacy', $field) && $this->modernProcessor !== null) {
            $result = $this->modernProcessor->validate($field, $value);
            if (! $result->isValid()) {
                return $this->invalid(implode(' ', $result->errors()));
            }
            return $this->valid($result->value());
        }

        $type = (string) ($field['type'] ?? 'text');

        $legacyValidationError = $this->legacyValidationError($field, $value);
        if ($legacyValidationError !== '') {
            return $this->invalid($legacyValidationError);
        }

        if ($type === 'text') {
            return $this->valid(sanitize_text_field((string) $value));
        }
        if ($type === 'icon') {
            $clean = sanitize_text_field((string) $value);
            if ($clean !== '' && (($field['legacy']['type'] ?? '') === 'fontawesome') && preg_match('/^fa-[a-z0-9-]+$/', $clean) !== 1) {
                return $this->invalid('legacy Font Awesome value must use the fa-* identifier format.');
            }
            return $this->valid($clean);
        }
        if ($type === 'textarea') {
            return $this->valid(sanitize_textarea_field((string) $value));
        }
        if ($type === 'editor') {
            return $this->valid(wp_kses_post((string) $value));
        }
        if ($type === 'code') {
            return $this->valid((string) $value);
        }
        if ($type === 'color') {
            $raw = trim((string) $value);
            if ($raw === '') {
                return $this->valid('');
            }
            $color = sanitize_hex_color($raw);
            return $color === null ? $this->invalid('invalid hexadecimal color.') : $this->valid($color);
        }
        if ($type === 'date') {
            $raw = sanitize_text_field((string) $value);
            if ($raw === '') {
                return $this->valid('');
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            return $date !== false && $date->format('Y-m-d') === $raw
                ? $this->valid($raw)
                : $this->invalid('date must use YYYY-MM-DD format.');
        }
        if (in_array($type, ['toggle', 'checkbox'], true)) {
            return $this->valid(in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true));
        }
        if ($type === 'range') {
            if (! is_numeric($value)) {
                return $this->invalid('range value must be numeric.');
            }
            $numeric = str_contains((string) $value, '.') ? (float) $value : (int) $value;
            $properties = (array) ($field['legacy']['properties'] ?? []);
            $min = isset($properties['min']) && is_numeric($properties['min']) ? (float) $properties['min'] : null;
            $max = isset($properties['max']) && is_numeric($properties['max']) ? (float) $properties['max'] : null;
            $step = isset($properties['step']) && is_numeric($properties['step']) ? (float) $properties['step'] : null;
            if ($min !== null && (float) $numeric < $min) {
                return $this->invalid('range value is below the legacy minimum.');
            }
            if ($max !== null && (float) $numeric > $max) {
                return $this->invalid('range value is above the legacy maximum.');
            }
            if ($step !== null && $step > 0) {
                $origin = $min ?? 0.0;
                $steps = (((float) $numeric) - $origin) / $step;
                if (abs($steps - round($steps)) > 0.000001) {
                    return $this->invalid('range value does not respect the legacy step.');
                }
            }
            return $this->valid($numeric);
        }
        if ($type === 'select') {
            $clean = sanitize_text_field((string) $value);
            $options = (array) ($field['options'] ?? []);
            if ($options !== [] && ! array_key_exists($clean, $options)) {
                return $this->invalid('selected value is not part of the static option list.');
            }
            return $this->valid($clean);
        }
        if ($type === 'media') {
            // Vafpress upload fields commonly stored URLs rather than attachment IDs.
            return $this->valid(esc_url_raw((string) $value));
        }
        if (in_array($type, ['multi_select', 'checkbox_group', 'image_checkbox_group', 'sortable_select'], true)) {
            if (! is_array($value)) {
                return $this->invalid('submitted selection must be an array.');
            }
            $clean = array_values(array_filter(array_map(static fn (mixed $item): string => sanitize_text_field((string) $item), $value), static fn (string $item): bool => $item !== ''));
            if ($type === 'sortable_select' && count($clean) !== count(array_unique($clean))) {
                return $this->invalid('sortable selection contains duplicated values.');
            }
            $options = (array) ($field['options'] ?? []);
            if ($options !== []) {
                foreach ($clean as $item) {
                    if (! array_key_exists($item, $options)) {
                        return $this->invalid('one or more selected values are not part of the static option list.');
                    }
                }
            }
            return $this->valid($clean);
        }

        return $this->invalid('field type is not editable in shadow mode.');
    }

    /** @param array<string,mixed> $field */
    private function legacyValidationError(array $field, mixed $value): string
    {
        $rules = array_values(array_filter(array_map('strval', (array) ($field['validation'] ?? []))));
        if ($rules === []) { return ''; }

        $isArray = is_array($value);
        $string = $isArray ? '' : trim((string) $value);
        $count = $isArray ? count(array_filter($value, static fn ($v): bool => $v !== '' && $v !== null)) : (function_exists('mb_strlen') ? mb_strlen($string) : strlen($string));

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '') { continue; }
            if ($rule === 'required') {
                if (($isArray && $count === 0) || (! $isArray && empty($value))) { return 'This field is required.'; }
                continue;
            }
            // Vafpress ignored format rules for empty values unless `required` was also present.
            if (($isArray && $count === 0) || (! $isArray && $string === '')) { continue; }
            if ($rule === 'alphabet' && ! $isArray && preg_match('/^[A-Z\s]+$/i', $string) !== 1) { return 'Value needs to be alphabetic.'; }
            if ($rule === 'alphanumeric' && ! $isArray && preg_match('/^[A-Z0-9]+$/i', $string) !== 1) { return 'Value needs to be alphanumeric.'; }
            if ($rule === 'numeric' && ! $isArray && preg_match('/^[-+]?[0-9]*\.?[0-9]+$/', $string) !== 1) { return 'Value needs to be numeric.'; }
            if ($rule === 'email' && ! $isArray && function_exists('is_email') && ! is_email($string)) { return 'Value needs to be a valid email address.'; }
            if ($rule === 'url' && ! $isArray && preg_match('~^(?:ftp|https?)://[^\s"]+$~i', $string) !== 1) { return 'Value needs to be a valid URL.'; }
            if (preg_match('/^maxlength\[(\d+)\]$/', $rule, $m) === 1 && $count > (int) $m[1]) { return sprintf('Length must be no more than %d.', (int) $m[1]); }
            if (preg_match('/^minlength\[(\d+)\]$/', $rule, $m) === 1 && $count < (int) $m[1]) { return sprintf('Length must be at least %d.', (int) $m[1]); }
            if (preg_match('/^maxselected\[(\d+)\]$/', $rule, $m) === 1 && $isArray && $count > (int) $m[1]) { return sprintf('Select no more than %d item(s).', (int) $m[1]); }
            if (preg_match('/^minselected\[(\d+)\]$/', $rule, $m) === 1 && $isArray && $count < (int) $m[1]) { return sprintf('Select at least %d item(s).', (int) $m[1]); }
        }
        return '';
    }

    /** @return array{valid:bool,value:mixed,message:string} */
    private function valid(mixed $value): array
    {
        return ['valid' => true, 'value' => $value, 'message' => ''];
    }

    /** @return array{valid:bool,value:mixed,message:string} */
    private function invalid(string $message): array
    {
        return ['valid' => false, 'value' => null, 'message' => $message];
    }

    /** @param array<string,mixed> $field */
    private function toLegacyShape(mixed $canonical, mixed $legacyValue, array $field): mixed
    {
        $type = (string) ($field['type'] ?? 'text');
        if (in_array($type, ['toggle', 'checkbox'], true)) {
            if (is_string($legacyValue)) {
                return (bool) $canonical ? '1' : '0';
            }
            if (is_int($legacyValue)) {
                return (bool) $canonical ? 1 : 0;
            }
            return (bool) $canonical;
        }
        if (in_array($type, ['multi_select', 'checkbox_group', 'image_checkbox_group', 'sortable_select'], true)) {
            if (! is_array($canonical)) {
                return $legacyValue;
            }
            $result = [];
            foreach ($canonical as $index => $item) {
                $prototype = is_array($legacyValue) && array_key_exists($index, $legacyValue) ? $legacyValue[$index] : null;
                $result[$index] = $this->castLike($item, $prototype);
            }
            return $result;
        }
        return $this->castLike($canonical, $legacyValue);
    }

    private function valuesEquivalent(mixed $submitted, mixed $legacy): bool
    {
        if (is_scalar($submitted) || $submitted === null) {
            if (is_scalar($legacy) || $legacy === null) {
                return (string) $submitted === (string) $legacy;
            }
        }

        return $submitted === $legacy;
    }

    private function castLike(mixed $value, mixed $prototype): mixed
    {
        if ($prototype === null) {
            return $value;
        }
        if (is_string($prototype)) {
            return is_scalar($value) || $value === null ? (string) $value : $value;
        }
        if (is_int($prototype)) {
            return is_numeric($value) ? (int) $value : $value;
        }
        if (is_float($prototype)) {
            return is_numeric($value) ? (float) $value : $value;
        }
        if (is_bool($prototype)) {
            return (bool) $value;
        }
        return $value;
    }

    /** @param array<mixed> $rows @param array<mixed> $children */
    private function repeaterRowsMatchSchema(array $rows, array $children): bool
    {
        $known = [];
        foreach ($children as $child) {
            if (is_array($child) && ! empty($child['name'])) {
                $known[] = (string) $child['name'];
            }
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }
            foreach (array_keys($row) as $key) {
                if (! in_array((string) $key, $known, true)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @param array<string,mixed> $schema @return array<mixed> */
    private function schemaFields(array $schema): array
    {
        if (($schema['kind'] ?? '') !== 'options') {
            return (array) ($schema['fields'] ?? []);
        }
        $fields = [];
        foreach ((array) ($schema['sections'] ?? []) as $section) {
            if (is_array($section)) {
                $fields = array_merge($fields, (array) ($section['fields'] ?? []));
            }
        }
        return $fields;
    }

    private function fingerprint(mixed $value): string
    {
        return substr(hash('sha256', serialize($value)), 0, 16);
    }

    private function summarize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value) || $value === null) {
            $text = (string) $value;
            return strlen($text) > 80 ? substr($text, 0, 77) . '…' : $text;
        }
        if (is_array($value)) {
            $allScalar = true;
            $parts = [];
            foreach ($value as $item) {
                if (! is_scalar($item) && $item !== null) {
                    $allScalar = false;
                    break;
                }
                $parts[] = (string) $item;
            }
            if ($allScalar && count($parts) <= 8) {
                return '[' . implode(', ', $parts) . ']';
            }
            return sprintf('[array: %d item(s)]', count($value));
        }
        return '[' . get_debug_type($value) . ']';
    }

    /** @return array<string,mixed> */
    private function unavailable(string $message): array
    {
        return [
            'status' => 'unavailable',
            'message' => $message,
            'submitted_fields' => 0,
            'accepted_fields' => 0,
            'changed_fields' => 0,
            'locked_fields' => 0,
            'rejected_fields' => 0,
            'ignored_input_keys' => 0,
            'changes' => [],
            'locked' => [],
            'rejected' => [],
            'ignored' => [],
            'legacy_hash' => '',
            'shadow_hash' => '',
            'payload_changed' => false,
            'form_values' => [],
            'baseline_gate_passed' => false,
            'submission_gate_passed' => false,
            'write_performed' => false,
        ];
    }
}
