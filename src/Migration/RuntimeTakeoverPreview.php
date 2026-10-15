<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class RuntimeTakeoverPreview
{
    /** @param array<string,mixed> $configuration @return array<string,mixed> */
    public function build(array $configuration): array
    {
        $readiness = (array) ($configuration['readiness'] ?? []);
        $plan = (array) ($configuration['plan'] ?? []);
        $shadowSave = (array) ($configuration['shadow_save'] ?? []);
        $config = (array) ($configuration['config'] ?? []);
        $schema = (array) ($configuration['schema'] ?? []);
        $ready = ($readiness['status'] ?? '') === 'ready';

        $kind = (string) ($configuration['kind'] ?? '');
        $registration = [];
        if ($kind === 'options') {
            $registration = [
                'kind' => 'options_page',
                'page_slug' => (string) ($config['page_slug'] ?? ''),
                'page_title' => (string) ($config['page_title'] ?? ''),
                'menu_label' => (string) ($config['menu_label'] ?? ''),
                'parent_menu' => (string) ($config['menu_page'] ?? ''),
                'capability' => (string) ($config['minimum_role'] ?? 'manage_options'),
            ];
        } elseif ($kind === 'metabox') {
            $registration = [
                'kind' => 'metabox',
                'id' => (string) ($config['id'] ?? ''),
                'title' => (string) ($config['title'] ?? ''),
                'post_types' => array_values(array_filter((array) ($config['types'] ?? []), 'is_string')),
                'context' => (string) ($config['context'] ?? 'normal'),
                'priority' => (string) ($config['priority'] ?? 'default'),
            ];
        }

        $hashes = (array) ($shadowSave['hashes'] ?? []);
        $storageFingerprint = '';
        if ($hashes !== []) {
            $first = reset($hashes);
            if (is_array($first)) {
                $storageFingerprint = (string) ($first['legacy'] ?? '');
            }
        }

        return [
            'status' => $ready ? 'ready' : 'blocked',
            'mount_preview_available' => $ready,
            'detach_legacy_allowed' => false,
            'dual_read_verified' => ! empty($shadowSave['write_gate_passed']),
            'storage_type' => (string) ($plan['storage']['type'] ?? ''),
            'storage_key' => (string) ($plan['storage']['key'] ?? ''),
            'storage_fingerprint' => $storageFingerprint,
            'schema_fingerprint' => substr(hash('sha256', serialize($schema)), 0, 16),
            'registration' => $registration,
            'message' => $ready
                ? 'Pressundo can mount a replacement renderer preview against the exact legacy storage contract. Legacy hooks remain attached; this advanced preview is optional and is not required by the Progressive Migration API.'
                : 'Takeover preview remains blocked until migration readiness reaches Ready.',
        ];
    }
}
