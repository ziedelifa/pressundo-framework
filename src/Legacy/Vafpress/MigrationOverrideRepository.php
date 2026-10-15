<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class MigrationOverrideRepository
{
    private const OPTION = 'pressundo_framework_vafpress_overrides';

    /** @var list<string> */
    private const FIELD_TARGETS = [
        'text', 'textarea', 'toggle', 'select', 'multi_select', 'checkbox_group',
        'range', 'media', 'date', 'color', 'code', 'icon', 'editor', 'sortable_select',
    ];

    /** @var array<string,string> */
    private const DATASOURCE_TARGETS = [
        'vp_get_categories' => 'WordPress categories',
        'vp_get_posts' => 'WordPress posts',
        'vp_get_pages' => 'WordPress pages',
        'vp_get_users' => 'WordPress users',
        'vp_get_tags' => 'WordPress tags',
        'vp_get_roles' => 'WordPress roles',
        'vp_get_social_medias' => 'Vafpress social network list',
    ];

    /** @return array{field_types:array<string,string>,datasources:array<string,string>} */
    public function all(): array
    {
        $raw = get_option(self::OPTION, []);
        if (! is_array($raw)) {
            $raw = [];
        }

        $fieldTypes = [];
        foreach ((array) ($raw['field_types'] ?? []) as $legacy => $target) {
            $legacyKey = sanitize_key((string) $legacy);
            $targetKey = sanitize_key((string) $target);
            if ($legacyKey !== '' && in_array($targetKey, self::FIELD_TARGETS, true)) {
                $fieldTypes[$legacyKey] = $targetKey;
            }
        }

        $datasources = [];
        foreach ((array) ($raw['datasources'] ?? []) as $legacy => $target) {
            $legacyKey = sanitize_key((string) $legacy);
            $targetKey = sanitize_key((string) $target);
            if ($legacyKey !== '' && array_key_exists($targetKey, self::DATASOURCE_TARGETS)) {
                $datasources[$legacyKey] = $targetKey;
            }
        }

        return ['field_types' => $fieldTypes, 'datasources' => $datasources];
    }

    public function fieldType(string $legacyType): ?string
    {
        $all = $this->all();
        $key = sanitize_key($legacyType);
        return $all['field_types'][$key] ?? null;
    }

    public function datasource(string $legacyFunction): ?string
    {
        $all = $this->all();
        $key = sanitize_key($legacyFunction);
        return $all['datasources'][$key] ?? null;
    }

    public function saveFieldType(string $legacyType, string $target): bool
    {
        $legacyType = sanitize_key($legacyType);
        $target = sanitize_key($target);
        if ($legacyType === '' || ! in_array($target, self::FIELD_TARGETS, true)) {
            return false;
        }
        $all = $this->all();
        $all['field_types'][$legacyType] = $target;
        return $this->persist($all);
    }

    public function saveDatasource(string $legacyFunction, string $target): bool
    {
        $legacyFunction = sanitize_key($legacyFunction);
        $target = sanitize_key($target);
        if ($legacyFunction === '' || ! array_key_exists($target, self::DATASOURCE_TARGETS)) {
            return false;
        }
        $all = $this->all();
        $all['datasources'][$legacyFunction] = $target;
        return $this->persist($all);
    }

    public function remove(string $kind, string $legacyKey): bool
    {
        $all = $this->all();
        $legacyKey = sanitize_key($legacyKey);
        if ($legacyKey === '') {
            return false;
        }
        if ($kind === 'field_type') {
            unset($all['field_types'][$legacyKey]);
        } elseif ($kind === 'datasource') {
            unset($all['datasources'][$legacyKey]);
        } else {
            return false;
        }
        return $this->persist($all);
    }

    /** @return list<string> */
    public function fieldTargets(): array
    {
        return self::FIELD_TARGETS;
    }

    /** @return array<string,string> */
    public function datasourceTargets(): array
    {
        return self::DATASOURCE_TARGETS;
    }

    /** @param array{field_types:array<string,string>,datasources:array<string,string>} $data */
    private function persist(array $data): bool
    {
        $current = get_option(self::OPTION, null);
        if ($current === null) {
            return add_option(self::OPTION, $data, '', false);
        }
        if ($current === $data) {
            return true;
        }
        return update_option(self::OPTION, $data, false);
    }
}
