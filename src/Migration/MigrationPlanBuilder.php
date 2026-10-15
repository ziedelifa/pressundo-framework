<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class MigrationPlanBuilder
{
    /** @param array<string,mixed> $configuration @return array<string,mixed> */
    public function build(array $configuration): array
    {
        $kind = (string) ($configuration['kind'] ?? 'unknown');
        $config = (array) ($configuration['config'] ?? []);
        $report = (array) ($configuration['report'] ?? []);
        $resolved = ! empty($configuration['resolved']);
        $manual = (int) ($report['manual'] ?? 0);
        $review = (int) ($report['review'] ?? 0);
        $parserWarnings = array_values(array_filter((array) ($configuration['warnings'] ?? []), 'is_string'));

        $storage = $kind === 'options'
            ? $this->optionStorage($config)
            : $this->metaboxStorage($config);

        $blockers = [];
        if (! $resolved) {
            $blockers[] = 'Legacy template is not safely resolvable yet.';
        }
        if ($manual > 0) {
            $blockers[] = sprintf('%d field(s) require a manual converter.', $manual);
        }
        if ($parserWarnings !== []) {
            $blockers[] = sprintf('%d safe-parser warning(s) must be reviewed before shadow mode.', count($parserWarnings));
        }

        $steps = [
            ['id' => 'register', 'label' => 'Register the converted schema in the modern Pressundo core.', 'write' => false],
            ['id' => 'shadow_read', 'label' => 'Read legacy data through the new storage adapter and compare normalized values.', 'write' => false],
            ['id' => 'render', 'label' => 'Render the modern admin UI while preserving the legacy storage key.', 'write' => false],
            ['id' => 'shadow_save', 'label' => 'Validate a no-change save output against legacy data shape.', 'write' => false],
            ['id' => 'shadow_submit', 'label' => 'Sanitize edited modern values and simulate a legacy-compatible payload without writing.', 'write' => false],
            ['id' => 'snapshot', 'label' => 'Before a controlled write, persist an encrypted rollback snapshot of the current legacy value.', 'write' => true],
            ['id' => 'controlled_write', 'label' => 'Write only after explicit administrator confirmation and a fresh gate check.', 'write' => true],
            ['id' => 'readback', 'label' => 'Read the value back and verify its fingerprint; automatically roll back if verification fails.', 'write' => true],
            ['id' => 'rollback', 'label' => 'Allow guarded rollback while refusing to overwrite newer third-party changes.', 'write' => true],
            ['id' => 'switch', 'label' => 'Disable the Vafpress renderer/runtime only after production compatibility checks pass.', 'write' => false],
        ];

        return [
            'strategy' => 'in_place_storage_compatibility',
            'storage' => $storage,
            'review_required' => $review > 0 || $parserWarnings !== [],
            'ready_for_shadow_mode' => $resolved && $manual === 0 && $parserWarnings === [],
            'ready_for_write_migration' => false,
            'blockers' => $blockers,
            'steps' => $steps,
        ];
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    private function optionStorage(array $config): array
    {
        $key = (string) ($config['option_key'] ?? '');
        return [
            'type' => 'wp_option',
            'key' => $key,
            'shape' => 'associative_array',
            'preserve_key' => $key !== '',
            'notes' => 'The modern renderer should continue reading/writing the same WordPress option key.',
        ];
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    private function metaboxStorage(array $config): array
    {
        $id = (string) ($config['id'] ?? '');
        $mode = strtolower((string) ($config['mode'] ?? 'array'));
        if (! in_array($mode, ['array', 'extract'], true)) {
            $mode = 'array';
        }
        $prefix = (string) ($config['prefix'] ?? '');

        if ($mode === 'extract') {
            return [
                'type' => 'post_meta_extract',
                'key' => $id,
                'field_prefix' => $prefix,
                'field_index_key' => $id !== '' ? $id . '_fields' : '',
                'shape' => 'individual_post_meta',
                'preserve_key' => $id !== '',
                'notes' => 'WPAlchemy extract mode stores each field separately and tracks keys in the metabox _fields entry.',
            ];
        }

        return [
            'type' => 'post_meta_array',
            'key' => $id,
            'shape' => 'associative_array',
            'preserve_key' => $id !== '',
            'notes' => 'Default Vafpress/WPAlchemy array mode stores the complete metabox under the metabox ID.',
        ];
    }
}
