<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class ShadowDataAnalyzer
{
    /** @param array<string,mixed> $configuration @return array<string,mixed> */
    public function analyze(array $configuration): array
    {
        if (empty($configuration['resolved']) || ! is_array($configuration['schema'] ?? null)) {
            return $this->unavailable('Schema is not safely resolved.');
        }

        $kind = (string) ($configuration['kind'] ?? '');
        $config = (array) ($configuration['config'] ?? []);
        $schema = (array) $configuration['schema'];

        if ($kind === 'options') {
            return $this->analyzeOption($config, $schema);
        }

        if ($kind === 'metabox') {
            return $this->analyzeMetabox($config, $schema);
        }

        return $this->unavailable('Unsupported legacy configuration kind.');
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $schema @return array<string,mixed> */
    private function analyzeOption(array $config, array $schema): array
    {
        $key = (string) ($config['option_key'] ?? '');
        if ($key === '') {
            return $this->unavailable('Legacy option key is missing.');
        }

        $sentinel = new \stdClass();
        $value = get_option($key, $sentinel);
        if ($value === $sentinel) {
            return $this->emptyResult('wp_option', $key, 'No stored option value was found.', []);
        }
        if (! is_array($value)) {
            return $this->invalidRoot('wp_option', $key, 'Stored option value is not an associative array.', $value);
        }

        $result = $this->inspectSchema($schema, $value);
        return $this->finish($result, [
            'storage' => 'wp_option',
            'key' => $key,
            'samples' => 1,
            'sample_ids' => [],
            'sample_values' => $value,
            'message' => 'Existing WordPress option data was compared with the normalized schema.',
        ]);
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $schema @return array<string,mixed> */
    private function analyzeMetabox(array $config, array $schema): array
    {
        $id = (string) ($config['id'] ?? '');
        if ($id === '') {
            return $this->unavailable('Legacy metabox ID is missing.');
        }

        $mode = strtolower((string) ($config['mode'] ?? 'array'));
        if (! in_array($mode, ['array', 'extract'], true)) {
            $mode = 'array';
        }

        $postTypes = array_values(array_filter((array) ($config['types'] ?? ['post']), 'is_string'));
        if ($postTypes === []) {
            $postTypes = ['post'];
        }

        if ($mode === 'extract') {
            return $this->analyzeExtractMetabox($id, (string) ($config['prefix'] ?? ''), $postTypes, $schema);
        }

        $ids = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'any',
            'posts_per_page' => 10,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'DESC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Debug-only bounded diagnostic query (10 IDs maximum).
            'meta_key' => $id,
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn (int $value): bool => $value > 0));

        if ($ids === []) {
            return $this->emptyResult('post_meta_array', $id, 'No posts containing this legacy metabox were found.', []);
        }

        $aggregate = $this->baseCounters();
        $firstValues = [];
        foreach ($ids as $index => $postId) {
            $value = get_post_meta($postId, $id, true);
            if (! is_array($value)) {
                ++$aggregate['conflicts'];
                ++$aggregate['checks'];
                $aggregate['issues'][] = sprintf('Post #%d: legacy metabox value is not an array.', $postId);
                continue;
            }
            if ($index === 0) {
                $firstValues = $value;
            }
            $aggregate = $this->mergeCounters($aggregate, $this->inspectSchema($schema, $value), sprintf('Post #%d', $postId));
        }

        return $this->finish($aggregate, [
            'storage' => 'post_meta_array',
            'key' => $id,
            'samples' => count($ids),
            'sample_ids' => $ids,
            'sample_values' => $firstValues,
            'message' => sprintf('%d content item(s) containing the legacy metabox were inspected.', count($ids)),
        ]);
    }

    /** @param list<string> $postTypes @param array<string,mixed> $schema @return array<string,mixed> */
    private function analyzeExtractMetabox(string $id, string $prefix, array $postTypes, array $schema): array
    {
        $fieldNames = $this->topLevelFieldNames($schema);
        $metaKeys = array_values(array_unique(array_filter(array_map(static fn (string $name): string => $prefix . $name, $fieldNames))));
        $indexKey = $id . '_fields';

        $metaQuery = ['relation' => 'OR'];
        $metaQuery[] = ['key' => $indexKey, 'compare' => 'EXISTS'];
        foreach (array_slice($metaKeys, 0, 20) as $metaKey) {
            $metaQuery[] = ['key' => $metaKey, 'compare' => 'EXISTS'];
        }

        $ids = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'any',
            'posts_per_page' => 10,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'DESC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Debug-only bounded diagnostic query (10 IDs maximum).
            'meta_query' => $metaQuery,
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn (int $value): bool => $value > 0));

        if ($ids === []) {
            return $this->emptyResult('post_meta_extract', $id, 'No posts containing extracted legacy fields were found.', []);
        }

        $aggregate = $this->baseCounters();
        $firstValues = [];
        foreach ($ids as $index => $postId) {
            $values = [];
            foreach ($fieldNames as $fieldName) {
                $key = $prefix . $fieldName;
                if (metadata_exists('post', $postId, $key)) {
                    $values[$fieldName] = get_post_meta($postId, $key, true);
                }
            }
            if ($index === 0) {
                $firstValues = $values;
            }
            $aggregate = $this->mergeCounters($aggregate, $this->inspectSchema($schema, $values), sprintf('Post #%d', $postId));
        }

        return $this->finish($aggregate, [
            'storage' => 'post_meta_extract',
            'key' => $id,
            'samples' => count($ids),
            'sample_ids' => $ids,
            'sample_values' => $firstValues,
            'message' => sprintf('%d content item(s) containing extracted legacy fields were inspected.', count($ids)),
        ]);
    }

    /** @param array<string,mixed> $schema @param array<string,mixed> $values @return array<string,mixed> */
    private function inspectSchema(array $schema, array $values): array
    {
        $counters = $this->baseCounters();
        $fields = [];
        if (($schema['kind'] ?? '') === 'options') {
            foreach ((array) ($schema['sections'] ?? []) as $section) {
                if (is_array($section)) {
                    $fields = array_merge($fields, (array) ($section['fields'] ?? []));
                }
            }
        } else {
            $fields = (array) ($schema['fields'] ?? []);
        }

        foreach ($fields as $field) {
            if (is_array($field)) {
                $this->inspectField($field, $values, $counters, '');
            }
        }

        return $counters;
    }

    /** @param array<string,mixed> $field @param array<string,mixed> $values @param array<string,mixed> $counters */
    private function inspectField(array $field, array $values, array &$counters, string $path): void
    {
        $name = (string) ($field['name'] ?? 'field');
        $currentPath = $path === '' ? $name : $path . '.' . $name;
        ++$counters['schema_fields'];

        if (! array_key_exists($name, $values)) {
            ++$counters['missing'];
            ++$counters['checks'];
            $counters['issues'][] = $currentPath . ': value is missing.';
            return;
        }

        $value = $values[$name];
        $type = (string) ($field['type'] ?? 'text');
        $migrationStatus = (string) ($field['migration']['status'] ?? 'automatic');

        if ($migrationStatus === 'manual' || $type === 'legacy_unknown') {
            ++$counters['conflicts'];
            ++$counters['checks'];
            $counters['issues'][] = $currentPath . ': no safe converter exists for this field type.';
            return;
        }

        if ($type === 'group') {
            $this->inspectGroup($field, $value, $counters, $currentPath);
            return;
        }

        $status = $this->classifyValue($field, $value);
        ++$counters[$status];
        ++$counters['checks'];
        if ($status === 'conflicts') {
            $counters['issues'][] = $currentPath . ': stored value is not compatible with the normalized field type.';
        } elseif ($status === 'normalized') {
            $counters['normalizations'][] = $currentPath . ': value can be normalized safely.';
        }
    }

    /** @param array<string,mixed> $field @param array<string,mixed> $counters */
    private function inspectGroup(array $field, mixed $value, array &$counters, string $path): void
    {
        $repeating = ! empty($field['repeating']);
        $children = (array) ($field['fields'] ?? []);

        if (! is_array($value)) {
            ++$counters['conflicts'];
            ++$counters['checks'];
            $counters['issues'][] = $path . ': group value is not an array.';
            return;
        }

        ++$counters['compatible'];
        ++$counters['checks'];

        if ($repeating) {
            foreach ($value as $rowIndex => $row) {
                if (! is_array($row)) {
                    ++$counters['conflicts'];
                    ++$counters['checks'];
                    $counters['issues'][] = sprintf('%s[%s]: repeater row is not an array.', $path, (string) $rowIndex);
                    continue;
                }
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $this->inspectField($child, $row, $counters, sprintf('%s[%s]', $path, (string) $rowIndex));
                    }
                }
            }
            return;
        }

        foreach ($children as $child) {
            if (is_array($child)) {
                $this->inspectField($child, $value, $counters, $path);
            }
        }
    }

    /** @param array<string,mixed> $field */
    private function classifyValue(array $field, mixed $value): string
    {
        $type = (string) ($field['type'] ?? 'text');

        if (in_array($type, ['text', 'textarea', 'code', 'editor', 'date', 'color', 'icon', 'html', 'notice'], true)) {
            return (is_scalar($value) || $value === null) ? 'compatible' : 'conflicts';
        }

        if (in_array($type, ['toggle', 'checkbox'], true)) {
            if (is_bool($value) || $value === 0 || $value === 1) {
                return 'compatible';
            }
            if (is_string($value) && in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off', ''], true)) {
                return 'normalized';
            }
            return 'conflicts';
        }

        if ($type === 'range') {
            return is_int($value) || is_float($value) ? 'compatible' : (is_numeric($value) ? 'normalized' : 'conflicts');
        }

        if (in_array($type, ['select', 'radio', 'image_radio'], true)) {
            if (! is_scalar($value) && $value !== null) {
                return 'conflicts';
            }
            $options = (array) ($field['options'] ?? []);
            if ($options !== [] && ! array_key_exists((string) $value, $options)) {
                return 'conflicts';
            }
            return 'compatible';
        }

        if (in_array($type, ['multi_select', 'checkbox_group', 'image_checkbox_group', 'sortable_select'], true)) {
            if (! is_array($value)) {
                return 'conflicts';
            }
            $options = (array) ($field['options'] ?? []);
            if ($options !== []) {
                foreach ($value as $item) {
                    if (! is_scalar($item) || ! array_key_exists((string) $item, $options)) {
                        return 'conflicts';
                    }
                }
            }
            return 'compatible';
        }

        if ($type === 'media') {
            return (is_scalar($value) || is_array($value) || $value === null) ? 'compatible' : 'conflicts';
        }

        return 'compatible';
    }

    /** @param array<string,mixed> $result @param array<string,mixed> $meta @return array<string,mixed> */
    private function finish(array $result, array $meta): array
    {
        $safeChecks = (int) $result['compatible'] + (int) $result['normalized'];
        $checks = (int) $result['checks'];
        $result['compatibility'] = $checks > 0 ? (int) round(($safeChecks / $checks) * 100) : 100;
        $result['status'] = ((int) $result['conflicts'] === 0) ? (((int) $result['missing'] === 0) ? 'ready' : 'review') : 'blocked';
        $result['read_only'] = true;
        return array_merge($result, $meta);
    }

    /** @return array<string,mixed> */
    private function baseCounters(): array
    {
        return [
            'schema_fields' => 0,
            'checks' => 0,
            'compatible' => 0,
            'normalized' => 0,
            'missing' => 0,
            'conflicts' => 0,
            'issues' => [],
            'normalizations' => [],
        ];
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private function mergeCounters(array $left, array $right, string $prefix = ''): array
    {
        foreach (['schema_fields', 'checks', 'compatible', 'normalized', 'missing', 'conflicts'] as $key) {
            $left[$key] = (int) ($left[$key] ?? 0) + (int) ($right[$key] ?? 0);
        }
        foreach (['issues', 'normalizations'] as $key) {
            foreach ((array) ($right[$key] ?? []) as $message) {
                $left[$key][] = $prefix !== '' ? $prefix . ' — ' . $message : $message;
            }
        }
        return $left;
    }

    /** @param array<string,mixed> $schema @return list<string> */
    private function topLevelFieldNames(array $schema): array
    {
        $names = [];
        $fields = (array) ($schema['fields'] ?? []);
        foreach ($fields as $field) {
            if (is_array($field) && ! empty($field['name'])) {
                $names[] = (string) $field['name'];
            }
        }
        return $names;
    }

    /** @return array<string,mixed> */
    private function unavailable(string $message): array
    {
        return [
            'status' => 'unavailable',
            'read_only' => true,
            'compatibility' => 0,
            'schema_fields' => 0,
            'checks' => 0,
            'compatible' => 0,
            'normalized' => 0,
            'missing' => 0,
            'conflicts' => 0,
            'issues' => [$message],
            'normalizations' => [],
            'samples' => 0,
            'sample_ids' => [],
            'sample_values' => [],
            'message' => $message,
        ];
    }

    /** @return array<string,mixed> */
    private function emptyResult(string $storage, string $key, string $message, array $sampleValues): array
    {
        return array_merge($this->baseCounters(), [
            'status' => 'no_data',
            'read_only' => true,
            'compatibility' => 100,
            'storage' => $storage,
            'key' => $key,
            'samples' => 0,
            'sample_ids' => [],
            'sample_values' => $sampleValues,
            'message' => $message,
        ]);
    }

    /** @return array<string,mixed> */
    private function invalidRoot(string $storage, string $key, string $message, mixed $value): array
    {
        $result = $this->baseCounters();
        $result['checks'] = 1;
        $result['conflicts'] = 1;
        $result['issues'][] = $message;
        return $this->finish($result, [
            'storage' => $storage,
            'key' => $key,
            'samples' => 1,
            'sample_ids' => [],
            'sample_values' => is_array($value) ? $value : [],
            'message' => $message,
        ]);
    }
}
