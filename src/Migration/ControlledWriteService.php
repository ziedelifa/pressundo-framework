<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class ControlledWriteService
{
    public function __construct(
        private readonly ShadowSubmissionSimulator $simulator,
        private readonly SnapshotRepository $snapshots
    ) {}

    /**
     * @param array<string,mixed> $configuration
     * @param array<string,mixed> $submitted
     * @return array<string,mixed>
     */
    public function apply(array $configuration, array $submitted, string $configurationId, int $postId = 0): array
    {
        $simulation = $this->simulator->simulate($configuration, $submitted);
        $base = [
            'status' => 'blocked',
            'write_performed' => false,
            'snapshot_id' => '',
            'target' => '',
            'before_hash' => (string) ($simulation['legacy_hash'] ?? ''),
            'after_hash' => '',
            'simulation' => $simulation,
            'message' => '',
        ];

        if (empty($simulation['submission_gate_passed'])) {
            $base['message'] = 'Controlled write blocked because the shadow submission gate did not pass.';
            return $base;
        }

        if ((int) ($simulation['changed_fields'] ?? 0) < 1) {
            $base['status'] = 'no_change';
            $base['message'] = 'No storage change was required, so no snapshot or database write was created.';
            return $base;
        }

        $plan = (array) ($configuration['plan'] ?? []);
        $storage = (array) ($plan['storage'] ?? []);
        $storageType = (string) ($storage['type'] ?? '');
        $storageKey = (string) ($storage['key'] ?? '');

        if (! in_array($storageType, ['wp_option', 'post_meta_array'], true) || $storageKey === '') {
            $base['message'] = 'This storage mode is not enabled for controlled writes in this development release.';
            return $base;
        }

        if ($storageType === 'post_meta_array') {
            $sampleIds = array_values(array_filter(array_map('intval', (array) ($configuration['shadow_data']['sample_ids'] ?? []))));
            $expectedPostId = (int) ($sampleIds[0] ?? 0);
            if ($expectedPostId < 1 || $postId !== $expectedPostId) {
                $base['message'] = 'Controlled write blocked because the requested post does not match the freshly sampled legacy record.';
                return $base;
            }
        }

        $current = $this->readCurrent($storageType, $storageKey, $postId);
        if (! $current['ok']) {
            $base['message'] = (string) $current['message'];
            return $base;
        }

        $base['target'] = (string) $current['target'];
        $currentPayload = $current['payload'];
        $currentHash = $this->fingerprint($currentPayload);
        if ($currentHash !== (string) ($simulation['legacy_hash'] ?? '')) {
            $base['status'] = 'stale';
            $base['before_hash'] = $currentHash;
            $base['message'] = 'Controlled write blocked because the storage value changed after the form was built. Re-analyze before trying again.';
            return $base;
        }

        $snapshotResult = $this->snapshots->create([
            'configuration_id' => $configurationId,
            'storage_type' => $storageType,
            'storage_key' => $storageKey,
            'post_id' => $postId,
            'existed' => (bool) $current['existed'],
            'before_hash' => $currentHash,
        ], $currentPayload);

        if (empty($snapshotResult['ok']) || ! is_array($snapshotResult['snapshot'] ?? null)) {
            $base['status'] = 'snapshot_failed';
            $base['message'] = (string) ($snapshotResult['message'] ?? 'Rollback snapshot creation failed.');
            return $base;
        }

        $snapshot = (array) $snapshotResult['snapshot'];
        $snapshotId = (string) ($snapshot['id'] ?? '');
        $base['snapshot_id'] = $snapshotId;
        $payload = $simulation['payload'] ?? null;

        $writeApiReportedSuccess = $this->write($storageType, $storageKey, $postId, $payload);
        $readBack = $this->readCurrent($storageType, $storageKey, $postId);
        $expectedHash = $this->fingerprint($payload);
        $actualHash = ! empty($readBack['ok']) ? $this->fingerprint($readBack['payload']) : '';
        $base['after_hash'] = $actualHash;
        $base['write_performed'] = true;

        // The read-back fingerprint is authoritative. WordPress write APIs can return
        // false for several reasons, so we never trust the return value alone.
        if (empty($readBack['ok']) || $actualHash !== $expectedHash) {
            $restored = $this->restoreRaw($storageType, $storageKey, $postId, $currentPayload, (bool) $current['existed']);
            $verify = $this->readCurrent($storageType, $storageKey, $postId);
            $rollbackOk = $restored && ! empty($verify['ok']) && $this->fingerprint($verify['payload']) === $currentHash;
            if ($rollbackOk) {
                $this->snapshots->markAutoRolledBack($snapshotId);
            }
            $base['status'] = $rollbackOk ? 'verification_failed_rolled_back' : 'verification_failed';
            $base['message'] = $rollbackOk
                ? 'Read-back verification failed, so Pressundo automatically restored the rollback snapshot.'
                : 'Read-back verification failed and the automatic rollback could not be verified. Manual intervention is required.';
            if (! $writeApiReportedSuccess) {
                $base['message'] .= ' The WordPress write API also reported failure.';
            }
            return $base;
        }

        $this->snapshots->markApplied($snapshotId, $actualHash);
        $base['status'] = 'success';
        $base['write_performed'] = true;
        $base['before_hash'] = $currentHash;
        $base['after_hash'] = $actualHash;
        $base['message'] = 'Controlled write completed and the stored payload was read back with the expected fingerprint. An encrypted rollback snapshot is available.';
        return $base;
    }

    /** @return array<string,mixed> */
    public function rollback(string $snapshotId): array
    {
        $snapshot = $this->snapshots->get($snapshotId);
        if (! is_array($snapshot)) {
            return $this->rollbackResult('missing', 'Rollback snapshot was not found.', $snapshotId);
        }

        if (($snapshot['state'] ?? '') !== 'applied') {
            return $this->rollbackResult('blocked', 'Only an applied snapshot can be rolled back.', $snapshotId);
        }

        $storageType = (string) ($snapshot['storage_type'] ?? '');
        $storageKey = (string) ($snapshot['storage_key'] ?? '');
        $postId = (int) ($snapshot['post_id'] ?? 0);
        $afterHash = (string) ($snapshot['after_hash'] ?? '');
        $beforeHash = (string) ($snapshot['before_hash'] ?? '');

        $current = $this->readCurrent($storageType, $storageKey, $postId);
        if (empty($current['ok'])) {
            return $this->rollbackResult('failed', (string) ($current['message'] ?? 'Current storage could not be read.'), $snapshotId);
        }

        $currentHash = $this->fingerprint($current['payload']);
        if ($afterHash === '' || $currentHash !== $afterHash) {
            return $this->rollbackResult(
                'stale',
                'Rollback blocked because the storage changed after the Pressundo write. This prevents overwriting newer changes.',
                $snapshotId,
                $currentHash,
                $afterHash
            );
        }

        try {
            $original = $this->snapshots->payload($snapshot);
        } catch (\Throwable $e) {
            return $this->rollbackResult('failed', $e->getMessage(), $snapshotId);
        }

        $restored = $this->restoreRaw($storageType, $storageKey, $postId, $original, ! empty($snapshot['existed']));
        $verify = $this->readCurrent($storageType, $storageKey, $postId);
        $actualHash = ! empty($verify['ok']) ? $this->fingerprint($verify['payload']) : '';

        if (! $restored || $actualHash !== $beforeHash) {
            return $this->rollbackResult('failed', 'Rollback write could not be verified against the original snapshot fingerprint.', $snapshotId, $actualHash, $beforeHash);
        }

        $this->snapshots->markRolledBack($snapshotId);
        return $this->rollbackResult('success', 'Rollback completed and the original payload fingerprint was restored.', $snapshotId, $actualHash, $beforeHash);
    }

    /** @return array<string,mixed> */
    private function readCurrent(string $storageType, string $storageKey, int $postId): array
    {
        if ($storageType === 'wp_option') {
            $sentinel = new \stdClass();
            $value = get_option($storageKey, $sentinel);
            $exists = $value !== $sentinel;
            return [
                'ok' => $exists,
                'payload' => $exists ? $value : null,
                'existed' => $exists,
                'target' => 'option:' . $storageKey,
                'message' => $exists ? '' : 'The legacy WordPress option no longer exists.',
            ];
        }

        if ($storageType === 'post_meta_array') {
            if ($postId < 1) {
                return ['ok' => false, 'message' => 'A valid post ID is required for this metabox write.'];
            }
            $exists = metadata_exists('post', $postId, $storageKey);
            return [
                'ok' => $exists,
                'payload' => $exists ? get_post_meta($postId, $storageKey, true) : null,
                'existed' => $exists,
                'target' => sprintf('post:%d meta:%s', $postId, $storageKey),
                'message' => $exists ? '' : 'The sampled legacy post meta no longer exists.',
            ];
        }

        return ['ok' => false, 'message' => 'Unsupported controlled-write storage type.'];
    }

    private function write(string $storageType, string $storageKey, int $postId, mixed $payload): bool
    {
        if ($storageType === 'wp_option') {
            return update_option($storageKey, $payload, false);
        }

        if ($storageType === 'post_meta_array') {
            return false !== update_post_meta($postId, $storageKey, $payload);
        }

        return false;
    }

    private function restoreRaw(string $storageType, string $storageKey, int $postId, mixed $payload, bool $existed): bool
    {
        if ($storageType === 'wp_option') {
            if (! $existed) {
                return delete_option($storageKey);
            }
            $result = update_option($storageKey, $payload, false);
            return $result || get_option($storageKey, null) === $payload;
        }

        if ($storageType === 'post_meta_array') {
            if (! $existed) {
                return delete_post_meta($postId, $storageKey);
            }
            $result = update_post_meta($postId, $storageKey, $payload);
            return $result !== false && get_post_meta($postId, $storageKey, true) === $payload;
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function rollbackResult(string $status, string $message, string $snapshotId, string $actualHash = '', string $expectedHash = ''): array
    {
        return [
            'status' => $status,
            'snapshot_id' => $snapshotId,
            'message' => $message,
            'actual_hash' => $actualHash,
            'expected_hash' => $expectedHash,
        ];
    }

    private function fingerprint(mixed $value): string
    {
        return substr(hash('sha256', serialize($value)), 0, 16);
    }
}
