<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration\Progressive;

use InvalidArgumentException;

final class ProgressiveMigrationService
{
    public const PRESERVE = 'preserve';
    public const LAZY = 'lazy';
    public const COPY = 'copy';

    public function __construct(private readonly StorageAccessor $storage)
    {
    }

    public function get(string $key, mixed $default = null, array $args = []): mixed
    {
        $context = $this->context($key, $args);
        $target = $context['target'];
        $legacy = $context['legacy'];
        $strategy = $context['strategy'];

        if ($this->storage->exists($target)) {
            return $this->storage->get($target, $default);
        }

        if ($legacy === null || $target->equals($legacy) || ! $this->storage->exists($legacy)) {
            return $default;
        }

        $legacyValue = $this->storage->get($legacy, $default);
        $value = $this->fromLegacy($legacyValue, $args, $legacy, $target);

        if ($strategy === self::PRESERVE) {
            return $value;
        }

        if ($this->storage->set($target, $value)) {
            $this->emitMigrated($legacy, $target, $strategy, $value);
        } else {
            $this->emitMigrationFailed($legacy, $target, $strategy, $value);
        }

        return $value;
    }

    public function set(string $key, mixed $value, array $args = []): bool
    {
        $context = $this->context($key, $args);
        $target = $context['target'];
        $legacy = $context['legacy'];
        $strategy = $context['strategy'];
        $value = $this->sanitize($value, $args);

        if ($legacy !== null && $target->equals($legacy)) {
            return $this->storage->set($target, $value);
        }

        if ($strategy === self::PRESERVE && $legacy !== null && ! $this->storage->exists($target) && $this->storage->exists($legacy)) {
            return $this->storage->set($legacy, $this->toLegacy($value, $args, $legacy, $target));
        }

        $copyLegacy = $strategy === self::COPY && $legacy !== null && $this->storage->exists($legacy);
        $legacyWriteValue = $copyLegacy ? $this->toLegacy($value, $args, $legacy, $target) : null;

        $targetExisted = $this->storage->exists($target);
        $previousTarget = $targetExisted ? $this->storage->get($target) : null;
        $targetResult = $this->storage->set($target, $value);
        if (! $targetResult) {
            return false;
        }

        if ($copyLegacy) {
            $legacyResult = $this->storage->set($legacy, $legacyWriteValue);
            if (! $legacyResult) {
                $this->restoreTarget($target, $targetExisted, $previousTarget);
                return false;
            }
        }

        return true;
    }

    public function delete(string $key, array $args = []): bool
    {
        $context = $this->context($key, $args);
        $target = $context['target'];
        $legacy = $context['legacy'];
        $strategy = $context['strategy'];

        if ($legacy !== null && $target->equals($legacy)) {
            return $this->storage->delete($target);
        }

        if ($strategy === self::PRESERVE && $legacy !== null && ! $this->storage->exists($target) && $this->storage->exists($legacy)) {
            return $this->storage->delete($legacy);
        }

        $result = $this->storage->delete($target);

        if (($args['delete_legacy'] ?? false) === true && $legacy !== null && $this->storage->exists($legacy)) {
            $result = $this->storage->delete($legacy) && $result;
        }

        return $result;
    }

    public function exists(string $key, array $args = []): bool
    {
        $context = $this->context($key, $args);
        if ($this->storage->exists($context['target'])) {
            return true;
        }

        return $context['legacy'] !== null && $this->storage->exists($context['legacy']);
    }

    /** @return array{target:StorageLocation,legacy:?StorageLocation,strategy:string} */
    private function context(string $key, array $args): array
    {
        if ($key === '') {
            throw new InvalidArgumentException('Value key cannot be empty.');
        }

        $strategy = (string) ($args['migration'] ?? self::PRESERVE);
        if (! in_array($strategy, [self::PRESERVE, self::LAZY, self::COPY], true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal and are not rendered as HTML.
            throw new InvalidArgumentException(sprintf('Unsupported migration strategy "%s".', $strategy));
        }

        $target = $this->locationFromArgs($key, $args, false);
        $legacy = $this->legacyLocation($key, $args, $target);

        return compact('target', 'legacy', 'strategy');
    }

    private function locationFromArgs(string $key, array $args, bool $legacy): StorageLocation
    {
        $prefix = $legacy ? 'legacy_' : '';
        $type = (string) ($args[$prefix . 'storage'] ?? $args['storage'] ?? 'option');
        $postId = isset($args[$prefix . 'post_id'])
            ? (int) $args[$prefix . 'post_id']
            : (isset($args['post_id']) ? (int) $args['post_id'] : null);
        $storageKey = (string) ($args[$prefix . 'storage_key'] ?? $key);
        $path = Path::normalize($args[$prefix . 'path'] ?? null);

        return new StorageLocation($type, $storageKey, $postId, $path);
    }

    private function legacyLocation(string $key, array $args, StorageLocation $target): ?StorageLocation
    {
        if (! array_key_exists('migrate_from', $args) || $args['migrate_from'] === null || $args['migrate_from'] === '') {
            return null;
        }

        $from = $args['migrate_from'];

        if (is_string($from) || is_int($from)) {
            $legacyArgs = $args;
            $legacyArgs['legacy_storage'] = $target->type;
            $legacyArgs['legacy_post_id'] = $target->postId;

            if ($target->path !== []) {
                // A namespace migration changes the container key while preserving the field path.
                $legacyArgs['legacy_storage_key'] = (string) $from;
                $legacyArgs['legacy_path'] = $target->path;
            } else {
                $legacyArgs['legacy_storage_key'] = (string) $from;
                $legacyArgs['legacy_path'] = [];
            }

            return $this->locationFromArgs($key, $legacyArgs, true);
        }

        if (! is_array($from)) {
            throw new InvalidArgumentException('migrate_from must be a storage key or an array descriptor.');
        }

        $legacyArgs = $args;
        $legacyArgs['legacy_storage'] = $from['storage'] ?? $target->type;
        $legacyArgs['legacy_post_id'] = $from['post_id'] ?? $target->postId;
        $legacyArgs['legacy_storage_key'] = $from['storage_key'] ?? $from['key'] ?? $key;
        $legacyArgs['legacy_path'] = $from['path'] ?? $target->path;

        return $this->locationFromArgs($key, $legacyArgs, true);
    }

    private function fromLegacy(mixed $value, array $args, StorageLocation $legacy, StorageLocation $target): mixed
    {
        $callback = $args['from_legacy'] ?? $args['transform'] ?? null;
        if (is_callable($callback)) {
            $value = $callback($value, $legacy, $target);
        }

        if (function_exists('apply_filters')) {
            $value = apply_filters('pressundo_progressive_migration_from_legacy', $value, $legacy, $target, $args);
        }

        return $value;
    }

    private function toLegacy(mixed $value, array $args, StorageLocation $legacy, StorageLocation $target): mixed
    {
        $callback = $args['to_legacy'] ?? null;
        if (is_callable($callback)) {
            $value = $callback($value, $legacy, $target);
        }

        if (function_exists('apply_filters')) {
            $value = apply_filters('pressundo_progressive_migration_to_legacy', $value, $legacy, $target, $args);
        }

        return $value;
    }

    private function sanitize(mixed $value, array $args): mixed
    {
        $callback = $args['sanitize'] ?? null;
        if (is_callable($callback)) {
            return $callback($value);
        }

        return $value;
    }

    private function restoreTarget(StorageLocation $target, bool $existed, mixed $previous): void
    {
        if ($existed) {
            $this->storage->set($target, $previous);
            return;
        }

        $this->storage->delete($target);
    }

    private function emitMigrated(StorageLocation $legacy, StorageLocation $target, string $strategy, mixed $value): void
    {
        if (function_exists('do_action')) {
            do_action('pressundo_progressive_value_migrated', $legacy, $target, $strategy, $value);
        }
    }

    private function emitMigrationFailed(StorageLocation $legacy, StorageLocation $target, string $strategy, mixed $value): void
    {
        if (function_exists('do_action')) {
            do_action('pressundo_progressive_value_migration_failed', $legacy, $target, $strategy, $value);
        }
    }
}
