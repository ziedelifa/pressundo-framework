<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class SnapshotRepository
{
    private const INDEX_OPTION = 'pressundo_framework_snapshot_index';
    private const SNAPSHOT_PREFIX = 'pressundo_framework_snapshot_';
    private const MAX_SNAPSHOTS = 20;

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function create(array $meta, mixed $payload): array
    {
        if (! function_exists('openssl_encrypt') || ! function_exists('openssl_decrypt')) {
            return [
                'ok' => false,
                'message' => 'Encrypted rollback snapshots require the OpenSSL PHP extension.',
            ];
        }

        if (! $this->isPortablePayload($payload)) {
            return [
                'ok' => false,
                'message' => 'Controlled write blocked because the legacy payload contains an object or resource that cannot be snapshotted and restored safely.',
            ];
        }

        try {
            $id = bin2hex(random_bytes(12));
            $encoded = $this->encrypt($payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Rollback snapshot encryption failed: ' . $e->getMessage(),
            ];
        }

        $snapshot = [
            'id' => $id,
            'version' => 1,
            'created_at' => gmdate('c'),
            'created_by' => get_current_user_id(),
            'state' => 'captured',
            'configuration_id' => (string) ($meta['configuration_id'] ?? ''),
            'storage_type' => (string) ($meta['storage_type'] ?? ''),
            'storage_key' => (string) ($meta['storage_key'] ?? ''),
            'post_id' => (int) ($meta['post_id'] ?? 0),
            'existed' => ! empty($meta['existed']),
            'before_hash' => (string) ($meta['before_hash'] ?? ''),
            'after_hash' => '',
            'payload' => $encoded,
        ];

        $optionName = self::SNAPSHOT_PREFIX . $id;
        if (! add_option($optionName, $snapshot, '', false)) {
            return [
                'ok' => false,
                'message' => 'Rollback snapshot could not be persisted.',
            ];
        }

        $index = $this->index();
        array_unshift($index, $id);
        $index = array_values(array_unique($index));

        // Prefer a compact history, but never auto-delete an `applied` snapshot
        // that is still the user's rollback point for a live controlled write.
        if (count($index) > self::MAX_SNAPSHOTS) {
            for ($position = count($index) - 1; $position >= 0 && count($index) > self::MAX_SNAPSHOTS; --$position) {
                $candidateId = (string) ($index[$position] ?? '');
                $candidate = $candidateId !== '' ? $this->get($candidateId) : null;
                if (is_array($candidate) && ($candidate['state'] ?? '') === 'applied') {
                    continue;
                }
                if ($candidateId !== '') {
                    delete_option(self::SNAPSHOT_PREFIX . $candidateId);
                }
                array_splice($index, $position, 1);
            }
        }

        update_option(self::INDEX_OPTION, array_values($index), false);

        return [
            'ok' => true,
            'snapshot' => $snapshot,
        ];
    }

    /** @return array<string,mixed>|null */
    public function get(string $id): ?array
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            return null;
        }

        $snapshot = get_option(self::SNAPSHOT_PREFIX . $id, null);
        return is_array($snapshot) ? $snapshot : null;
    }

    public function payload(array $snapshot): mixed
    {
        $encoded = $snapshot['payload'] ?? null;
        if (! is_array($encoded)) {
            throw new \RuntimeException('Snapshot payload is unavailable.');
        }

        return $this->decrypt($encoded);
    }

    public function markApplied(string $id, string $afterHash): bool
    {
        return $this->updateState($id, 'applied', $afterHash);
    }

    public function markRolledBack(string $id): bool
    {
        return $this->updateState($id, 'rolled_back');
    }

    public function markAutoRolledBack(string $id): bool
    {
        return $this->updateState($id, 'auto_rolled_back');
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 10): array
    {
        $out = [];
        foreach (array_slice($this->index(), 0, max(1, $limit)) as $id) {
            $snapshot = $this->get($id);
            if (! is_array($snapshot)) {
                continue;
            }
            unset($snapshot['payload']);
            $out[] = $snapshot;
        }
        return $out;
    }

    /** @return list<string> */
    private function index(): array
    {
        $index = get_option(self::INDEX_OPTION, []);
        if (! is_array($index)) {
            return [];
        }

        return array_values(array_filter(array_map([$this, 'sanitizeId'], $index), static fn (string $id): bool => $id !== ''));
    }

    private function updateState(string $id, string $state, string $afterHash = ''): bool
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            return false;
        }

        $optionName = self::SNAPSHOT_PREFIX . $id;
        $snapshot = get_option($optionName, null);
        if (! is_array($snapshot)) {
            return false;
        }

        $snapshot['state'] = $state;
        if ($afterHash !== '') {
            $snapshot['after_hash'] = $afterHash;
        }
        $snapshot['updated_at'] = gmdate('c');

        return update_option($optionName, $snapshot, false) || get_option($optionName, null) === $snapshot;
    }

    /** @return array{cipher:string,iv:string,tag:string,alg:string} */
    private function encrypt(mixed $payload): array
    {
        $plain = serialize($payload);
        $iv = random_bytes(12);
        $tag = '';
        $key = hash('sha256', wp_salt('auth'), true);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || $tag === '') {
            throw new \RuntimeException('OpenSSL could not encrypt the snapshot.');
        }

        return [
            'alg' => 'aes-256-gcm',
            'cipher' => base64_encode($cipher),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
        ];
    }

    private function decrypt(array $encoded): mixed
    {
        if (($encoded['alg'] ?? '') !== 'aes-256-gcm') {
            throw new \RuntimeException('Unsupported snapshot encryption format.');
        }

        $cipher = base64_decode((string) ($encoded['cipher'] ?? ''), true);
        $iv = base64_decode((string) ($encoded['iv'] ?? ''), true);
        $tag = base64_decode((string) ($encoded['tag'] ?? ''), true);
        if ($cipher === false || $iv === false || $tag === false) {
            throw new \RuntimeException('Snapshot encryption metadata is invalid.');
        }

        $key = hash('sha256', wp_salt('auth'), true);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Snapshot could not be decrypted. The site salts may have changed.');
        }

        return unserialize($plain, ['allowed_classes' => false]);
    }

    private function isPortablePayload(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (! is_int($key) && ! is_string($key)) {
                    return false;
                }
                if (! $this->isPortablePayload($item)) {
                    return false;
                }
            }
            return true;
        }

        return $value === null || is_scalar($value);
    }

    private function sanitizeId(mixed $id): string
    {
        $id = strtolower((string) $id);
        return preg_match('/^[a-f0-9]{8,32}$/', $id) === 1 ? $id : '';
    }
}
