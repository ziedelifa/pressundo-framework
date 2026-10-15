<?php

declare(strict_types=1);

namespace Pressundo\Framework\Admin;

final class AssetManager
{
    private bool $baseEnqueued = false;
    private bool $mediaEnqueued = false;

    /** @param array<string,mixed> $schema */
    public function enqueueForSchema(array $schema, bool $forceMedia = false): void
    {
        $this->enqueueBase();
        if ($forceMedia || $this->schemaNeedsMedia($schema)) {
            $this->enqueueMedia();
        }
    }

    public function enqueueBase(): void
    {
        if ($this->baseEnqueued) {
            return;
        }
        $this->baseEnqueued = true;

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pressundo-framework-admin',
                PRESSUNDO_FRAMEWORK_URL . 'assets/css/admin.css',
                [],
                PRESSUNDO_FRAMEWORK_VERSION
            );
        }
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'pressundo-framework-admin',
                PRESSUNDO_FRAMEWORK_URL . 'assets/js/admin.js',
                [],
                PRESSUNDO_FRAMEWORK_VERSION,
                true
            );
        }
    }

    public function enqueueStylesOnly(): void
    {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'pressundo-framework-admin',
                PRESSUNDO_FRAMEWORK_URL . 'assets/css/admin.css',
                [],
                PRESSUNDO_FRAMEWORK_VERSION
            );
        }
    }

    private function enqueueMedia(): void
    {
        if ($this->mediaEnqueued) {
            return;
        }
        $this->mediaEnqueued = true;
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
    }

    /** @param array<string,mixed> $schema */
    public function schemaNeedsMedia(array $schema): bool
    {
        if ((array) ($schema['sections'] ?? []) !== []) {
            foreach ((array) ($schema['sections'] ?? []) as $section) {
                if (is_array($section) && $this->fieldsNeedMedia((array) ($section['fields'] ?? []))) {
                    return true;
                }
            }
            return false;
        }
        return $this->fieldsNeedMedia((array) ($schema['fields'] ?? []));
    }

    /** @param array<mixed> $fields */
    private function fieldsNeedMedia(array $fields): bool
    {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $type = (string) ($field['type'] ?? '');
            if (in_array($type, ['media', 'gallery', 'background'], true)) {
                return true;
            }
            if ($type === 'group' && $this->fieldsNeedMedia((array) ($field['fields'] ?? []))) {
                return true;
            }
        }
        return false;
    }
}
