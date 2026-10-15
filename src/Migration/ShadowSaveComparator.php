<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

/**
 * Simulates a no-change save through the modern schema and legacy storage
 * contract. Nothing is written to WordPress.
 */
final class ShadowSaveComparator
{
    /** @param array<string,mixed> $configuration @param array<string,mixed> $shadowData @return array<string,mixed> */
    public function compare(array $configuration, array $shadowData): array
    {
        if (empty($configuration['resolved']) || ! is_array($configuration['schema'] ?? null)) {
            return $this->unavailable('Schema is not safely resolved.');
        }

        $shadowStatus = (string) ($shadowData['status'] ?? 'unavailable');
        if (in_array($shadowStatus, ['unavailable', 'no_data'], true)) {
            return $this->unavailable((string) ($shadowData['message'] ?? 'No legacy data is available for round-trip simulation.'), $shadowStatus);
        }

        $kind = (string) ($configuration['kind'] ?? '');
        $config = (array) ($configuration['config'] ?? []);
        $schema = (array) $configuration['schema'];

        if ($kind === 'options') {
            return $this->compareOption($config, $schema);
        }
        if ($kind === 'metabox') {
            return $this->compareMetabox($config, $schema, (array) ($shadowData['sample_ids'] ?? []));
        }

        return $this->unavailable('Unsupported legacy configuration kind.');
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $schema @return array<string,mixed> */
    private function compareOption(array $config, array $schema): array
    {
        $key = (string) ($config['option_key'] ?? '');
        if ($key === '') {
            return $this->unavailable('Legacy option key is missing.');
        }

        $sentinel = new \stdClass();
        $legacy = get_option($key, $sentinel);
        if ($legacy === $sentinel || ! is_array($legacy)) {
            return $this->unavailable('Legacy option data is unavailable or is not an array.');
        }

        $sample = $this->compareSample($schema, $legacy, 'option:' . $key);
        return $this->finish($sample, [
            'storage' => 'wp_option',
            'key' => $key,
            'samples' => 1,
            'sample_ids' => [],
            'message' => 'A no-change modern save was simulated against the existing WordPress option value.',
        ]);
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $schema @param array<mixed> $sampleIds @return array<string,mixed> */
    private function compareMetabox(array $config, array $schema, array $sampleIds): array
    {
        $id = (string) ($config['id'] ?? '');
        if ($id === '') {
            return $this->unavailable('Legacy metabox ID is missing.');
        }

        $ids = array_values(array_filter(array_map('intval', $sampleIds), static fn (int $value): bool => $value > 0));
        if ($ids === []) {
            return $this->unavailable('No sampled legacy metabox values are available.', 'no_data');
        }

        $mode = strtolower((string) ($config['mode'] ?? 'array'));
        if (! in_array($mode, ['array', 'extract'], true)) {
            $mode = 'array';
        }
        $prefix = (string) ($config['prefix'] ?? '');
        $aggregate = $this->baseResult();

        foreach ($ids as $postId) {
            if ($mode === 'extract') {
                $legacy = $this->readExtractValues($postId, $prefix, $schema);
                $sample = $this->compareSample($schema, $legacy, 'post:' . $postId);
            } else {
                $legacy = get_post_meta($postId, $id, true);
                if (! is_array($legacy)) {
                    $sample = $this->baseResult();
                    ++$sample['storage_differences'];
                    $sample['differences'][] = sprintf('Post #%d: legacy metabox root is not an array.', $postId);
                    $sample['hashes'][] = [
                        'sample' => 'post:' . $postId,
                        'legacy' => '',
                        'shadow' => '',
                        'match' => false,
                    ];
                } else {
                    $sample = $this->compareSample($schema, $legacy, 'post:' . $postId);
                }
            }
            $aggregate = $this->merge($aggregate, $sample);
        }

        return $this->finish($aggregate, [
            'storage' => $mode === 'extract' ? 'post_meta_extract' : 'post_meta_array',
            'key' => $id,
            'samples' => count($ids),
            'sample_ids' => $ids,
            'message' => sprintf('%d sampled content item(s) were round-trip simulated without writing post meta.', count($ids)),
        ]);
    }

    /** @param array<string,mixed> $schema @param array<string,mixed> $legacy @return array<string,mixed> */
    private function compareSample(array $schema, array $legacy, string $sampleLabel): array
    {
        $result = $this->baseResult();
        $shadow = $legacy; // Preserve unknown/unmanaged legacy keys by default.
        $schemaNames = [];

        foreach ($this->schemaFields($schema) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $schemaNames[] = $name;
            if (! array_key_exists($name, $legacy)) {
                ++$result['missing_values'];
                $result['notes'][] = $name . ': absent legacy value remains absent during shadow save.';
                continue;
            }
            $shadow[$name] = $this->roundTripField($field, $legacy[$name], $result, $name);
        }

        foreach (array_keys($legacy) as $legacyKey) {
            if (! in_array((string) $legacyKey, $schemaNames, true)) {
                ++$result['passthrough_keys'];
            }
        }

        $legacyHash = $this->payloadHash($legacy);
        $shadowHash = $this->payloadHash($shadow);
        $match = $legacy === $shadow;
        if (! $match) {
            ++$result['storage_differences'];
            foreach ($this->diffPaths($legacy, $shadow) as $difference) {
                $result['differences'][] = $sampleLabel . ' — ' . $difference;
            }
        }

        $result['hashes'][] = [
            'sample' => $sampleLabel,
            'legacy' => $legacyHash,
            'shadow' => $shadowHash,
            'match' => $match,
        ];

        return $result;
    }

    /** @param array<string,mixed> $field @param array<string,mixed> $result */
    private function roundTripField(array $field, mixed $legacyValue, array &$result, string $path): mixed
    {
        $status = (string) ($field['migration']['status'] ?? 'automatic');
        $type = (string) ($field['type'] ?? 'text');

        if ($status === 'manual' || $type === 'legacy_unknown') {
            ++$result['blocked_fields'];
            $result['blocked'][] = $path . ': manual field is preserved byte-for-byte but cannot be edited safely yet.';
            return $legacyValue;
        }

        if ($type === 'group') {
            if (! is_array($legacyValue)) {
                ++$result['storage_differences'];
                $result['differences'][] = $path . ': group is not an array.';
                return $legacyValue;
            }

            $children = (array) ($field['fields'] ?? []);
            if (! empty($field['repeating'])) {
                $rows = [];
                foreach ($legacyValue as $index => $row) {
                    if (! is_array($row)) {
                        $rows[$index] = $row;
                        ++$result['storage_differences'];
                        $result['differences'][] = sprintf('%s[%s]: repeater row is not an array.', $path, (string) $index);
                        continue;
                    }
                    $rows[$index] = $this->roundTripChildren($children, $row, $result, sprintf('%s[%s]', $path, (string) $index));
                }
                return $rows;
            }
            return $this->roundTripChildren($children, $legacyValue, $result, $path);
        }

        ++$result['compared_values'];
        $canonical = $this->toCanonical($field, $legacyValue);
        if ($canonical !== $legacyValue) {
            ++$result['canonical_changes'];
            $result['canonical_change_details'][] = $path . ': modern canonical representation differs, legacy storage shape will be preserved.';
        }

        $serialized = $this->toLegacyShape($canonical, $legacyValue, $field);
        if ($serialized === $legacyValue) {
            ++$result['storage_identical'];
        }
        return $serialized;
    }

    /** @param array<mixed> $children @param array<string,mixed> $legacy @param array<string,mixed> $result @return array<string,mixed> */
    private function roundTripChildren(array $children, array $legacy, array &$result, string $path): array
    {
        $shadow = $legacy;
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
            if (! array_key_exists($name, $legacy)) {
                ++$result['missing_values'];
                continue;
            }
            $shadow[$name] = $this->roundTripField($child, $legacy[$name], $result, $path . '.' . $name);
        }
        foreach (array_keys($legacy) as $legacyKey) {
            if (! in_array((string) $legacyKey, $known, true)) {
                ++$result['passthrough_keys'];
            }
        }
        return $shadow;
    }

    /** @param array<string,mixed> $field */
    private function toCanonical(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        if (in_array($type, ['text', 'select', 'radio', 'image_radio', 'date', 'icon'], true)) {
            return sanitize_text_field((string) $value);
        }
        if ($type === 'textarea') {
            return sanitize_textarea_field((string) $value);
        }
        if ($type === 'editor') {
            return wp_kses_post((string) $value);
        }
        if ($type === 'code') {
            return (string) $value;
        }
        if ($type === 'color') {
            $color = sanitize_hex_color((string) $value);
            return $color !== null ? $color : (string) $value;
        }
        if (in_array($type, ['toggle', 'checkbox'], true)) {
            if (is_string($value)) {
                return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
            }
            return (bool) $value;
        }
        if ($type === 'range') {
            if (is_int($value) || is_float($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return str_contains((string) $value, '.') ? (float) $value : (int) $value;
            }
            return $value;
        }
        if (in_array($type, ['multi_select', 'checkbox_group', 'image_checkbox_group', 'sortable_select'], true)) {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): string => sanitize_text_field((string) $item), $value)) : [];
        }
        if ($type === 'media') {
            return $value;
        }

        return $value;
    }

    /** @param array<string,mixed> $field */
    private function toLegacyShape(mixed $canonical, mixed $legacyValue, array $field): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        if (in_array($type, ['multi_select', 'checkbox_group', 'image_checkbox_group', 'sortable_select'], true)) {
            if (! is_array($canonical)) {
                return $legacyValue;
            }
            $result = [];
            foreach ($canonical as $index => $item) {
                $original = is_array($legacyValue) && array_key_exists($index, $legacyValue) ? $legacyValue[$index] : null;
                $result[$index] = $this->castLike($item, $original);
            }
            return $result;
        }

        if (in_array($type, ['toggle', 'checkbox'], true)) {
            if (is_string($legacyValue)) {
                return (bool) $canonical ? '1' : '0';
            }
            if (is_int($legacyValue)) {
                return (bool) $canonical ? 1 : 0;
            }
            return (bool) $canonical;
        }

        return $this->castLike($canonical, $legacyValue);
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

    /** @param array<string,mixed> $schema @return array<string,mixed> */
    private function readExtractValues(int $postId, string $prefix, array $schema): array
    {
        $values = [];
        foreach ($this->schemaFields($schema) as $field) {
            if (! is_array($field) || empty($field['name'])) {
                continue;
            }
            $name = (string) $field['name'];
            $key = $prefix . $name;
            if (metadata_exists('post', $postId, $key)) {
                $values[$name] = get_post_meta($postId, $key, true);
            }
        }
        return $values;
    }

    /** @return array<string,mixed> */
    private function baseResult(): array
    {
        return [
            'compared_values' => 0,
            'storage_identical' => 0,
            'canonical_changes' => 0,
            'storage_differences' => 0,
            'blocked_fields' => 0,
            'missing_values' => 0,
            'passthrough_keys' => 0,
            'canonical_change_details' => [],
            'differences' => [],
            'blocked' => [],
            'notes' => [],
            'hashes' => [],
        ];
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right @return array<string,mixed> */
    private function merge(array $left, array $right): array
    {
        foreach (['compared_values', 'storage_identical', 'canonical_changes', 'storage_differences', 'blocked_fields', 'missing_values', 'passthrough_keys'] as $key) {
            $left[$key] = (int) ($left[$key] ?? 0) + (int) ($right[$key] ?? 0);
        }
        foreach (['canonical_change_details', 'differences', 'blocked', 'notes', 'hashes'] as $key) {
            $left[$key] = array_merge((array) ($left[$key] ?? []), (array) ($right[$key] ?? []));
        }
        return $left;
    }

    /** @param array<string,mixed> $result @param array<string,mixed> $meta @return array<string,mixed> */
    private function finish(array $result, array $meta): array
    {
        $hashes = (array) ($result['hashes'] ?? []);
        $allHashesMatch = $hashes !== [];
        foreach ($hashes as $hash) {
            if (! is_array($hash) || empty($hash['match'])) {
                $allHashesMatch = false;
                break;
            }
        }

        $blocked = (int) ($result['blocked_fields'] ?? 0) > 0;
        $different = (int) ($result['storage_differences'] ?? 0) > 0 || ! $allHashesMatch;
        $status = $different ? 'failed' : ($blocked ? 'blocked' : 'pass');

        return array_merge($result, $meta, [
            'status' => $status,
            'read_only' => true,
            'payloads_identical' => $allHashesMatch,
            'write_gate_passed' => $status === 'pass',
        ]);
    }

    /** @return array<string,mixed> */
    private function unavailable(string $message, string $status = 'unavailable'): array
    {
        return array_merge($this->baseResult(), [
            'status' => $status,
            'read_only' => true,
            'payloads_identical' => false,
            'write_gate_passed' => false,
            'samples' => 0,
            'sample_ids' => [],
            'message' => $message,
        ]);
    }

    private function payloadHash(mixed $payload): string
    {
        return substr(hash('sha256', maybe_serialize($payload)), 0, 16);
    }

    /** @return list<string> */
    private function diffPaths(mixed $legacy, mixed $shadow, string $path = ''): array
    {
        if ($legacy === $shadow) {
            return [];
        }
        if (! is_array($legacy) || ! is_array($shadow)) {
            return [$path !== '' ? $path : 'root payload differs after round-trip.'];
        }

        $differences = [];
        $keys = array_values(array_unique(array_merge(array_keys($legacy), array_keys($shadow))));
        foreach ($keys as $key) {
            $childPath = $path === '' ? (string) $key : $path . '.' . (string) $key;
            if (! array_key_exists($key, $legacy)) {
                $differences[] = $childPath . ': added by shadow payload.';
                continue;
            }
            if (! array_key_exists($key, $shadow)) {
                $differences[] = $childPath . ': missing from shadow payload.';
                continue;
            }
            $differences = array_merge($differences, $this->diffPaths($legacy[$key], $shadow[$key], $childPath));
        }
        return $differences;
    }
}
