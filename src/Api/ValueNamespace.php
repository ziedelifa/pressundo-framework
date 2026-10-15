<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

use Pressundo\Framework\Migration\Progressive\Path;
use Pressundo\Framework\Migration\Progressive\ProgressiveMigrationService;

final class ValueNamespace
{
    public function __construct(
        private readonly ProgressiveMigrationService $migration,
        private readonly string $name,
        private readonly array $config,
    ) {
    }

    public function get(string $key, mixed $default = null, array $args = []): mixed
    {
        return $this->migration->get($key, $default, $this->fieldArgs($key, $args));
    }

    public function set(string $key, mixed $value, array $args = []): bool
    {
        return $this->migration->set($key, $value, $this->fieldArgs($key, $args));
    }

    public function delete(string $key, array $args = []): bool
    {
        return $this->migration->delete($key, $this->fieldArgs($key, $args));
    }

    public function exists(string $key, array $args = []): bool
    {
        return $this->migration->exists($key, $this->fieldArgs($key, $args));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function config(): array
    {
        return $this->config;
    }

    private function fieldArgs(string $key, array $args): array
    {
        $merged = array_replace($this->config, $args);
        $merged['storage_key'] = (string) ($this->config['storage_key'] ?? $this->name);
        $basePath = Path::normalize($this->config['path'] ?? null);
        $fieldPath = Path::normalize($args['path'] ?? $key);
        $merged['path'] = array_merge($basePath, $fieldPath);

        if (array_key_exists('migrate_from', $args)) {
            $from = $args['migrate_from'];
            if (is_string($from) || is_int($from)) {
                $legacyContainer = $this->config['migrate_from'] ?? $merged['storage_key'];
                if (is_array($legacyContainer)) {
                    $descriptor = $legacyContainer;
                    $descriptor['path'] = array_merge(
                        Path::normalize($descriptor['path'] ?? null),
                        Path::normalize((string) $from)
                    );
                } else {
                    $descriptor = [
                        'storage' => $this->config['legacy_storage'] ?? $this->config['storage'] ?? 'option',
                        'post_id' => $this->config['legacy_post_id'] ?? $this->config['post_id'] ?? null,
                        'storage_key' => (string) $legacyContainer,
                        'path' => array_merge($basePath, Path::normalize((string) $from)),
                    ];
                }
                $merged['migrate_from'] = $descriptor;
            }

            return $merged;
        }

        if (array_key_exists('migrate_from', $this->config)) {
            $from = $this->config['migrate_from'];
            if (is_string($from) || is_int($from)) {
                $merged['migrate_from'] = [
                    'storage' => $this->config['legacy_storage'] ?? $this->config['storage'] ?? 'option',
                    'post_id' => $this->config['legacy_post_id'] ?? $this->config['post_id'] ?? null,
                    'storage_key' => (string) $from,
                    'path' => $merged['path'],
                ];
            } elseif (is_array($from)) {
                $from['path'] = array_merge(Path::normalize($from['path'] ?? null), $fieldPath);
                $merged['migrate_from'] = $from;
            }
        }

        return $merged;
    }
}
