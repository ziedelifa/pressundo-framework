<?php

declare(strict_types=1);

namespace Pressundo\Framework\Admin;

use Pressundo\Framework\Api\FieldValueProcessor;
use Pressundo\Framework\Renderer\SchemaFormRenderer;
use Pressundo\Framework\Security\SecurityPolicy;

/**
 * Modern public metabox registration API.
 *
 * Values are stored as one associative array in a single post-meta key so the
 * schema can evolve without scattering internal bookkeeping across many rows.
 */
final class Metabox
{
    /** @var array<string,mixed> */
    private array $config;

    /** @var array<string,mixed> */
    private array $schema;

    public function __construct(
        array $config,
        private readonly AssetManager $assets,
        private readonly SchemaFormRenderer $renderer,
        private readonly FieldValueProcessor $processor
    ) {
        $id = sanitize_key((string) ($config['id'] ?? 'pressundo-metabox')) ?: 'pressundo-metabox';
        $title = trim((string) ($config['title'] ?? 'Pressundo Metabox')) ?: 'Pressundo Metabox';
        $postTypes = $config['post_types'] ?? $config['post_type'] ?? ['post'];
        $postTypes = is_array($postTypes) ? $postTypes : [$postTypes];
        $postTypes = array_values(array_filter(array_map('sanitize_key', array_map('strval', $postTypes))));
        if ($postTypes === []) {
            $postTypes = ['post'];
        }

        $this->config = array_replace([
            'id' => $id,
            'title' => $title,
            'post_types' => $postTypes,
            'context' => 'normal',
            'priority' => 'high',
            'storage_key' => '_' . $id,
            'ui_theme' => 'air',
            'sections' => [],
            'fields' => [],
        ], $config);
        $this->config['id'] = $id;
        $this->config['title'] = $title;
        $this->config['post_types'] = $postTypes;

        $sections = array_values(array_filter((array) $this->config['sections'], 'is_array'));
        $fields = array_values(array_filter((array) $this->config['fields'], 'is_array'));
        $this->schema = [
            'id' => $id,
            'kind' => 'metabox',
            'title' => $title,
            'ui_theme' => sanitize_key((string) $this->config['ui_theme']) ?: 'air',
            'sections' => $sections,
            'fields' => $fields,
        ];

        add_action('add_meta_boxes', [$this, 'register']);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function register(): void
    {
        foreach ((array) $this->config['post_types'] as $postType) {
            add_meta_box(
                (string) $this->config['id'],
                (string) $this->config['title'],
                [$this, 'render'],
                (string) $postType,
                (string) $this->config['context'],
                (string) $this->config['priority']
            );
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $postType = is_object($screen) && ! empty($screen->post_type) ? (string) $screen->post_type : '';
        if ($postType !== '' && ! in_array($postType, (array) $this->config['post_types'], true)) {
            return;
        }
        $this->assets->enqueueForSchema($this->schema);
    }

    public function render(object $post): void
    {
        $postId = isset($post->ID) ? (int) $post->ID : 0;
        $values = $postId > 0 ? get_post_meta($postId, (string) $this->config['storage_key'], true) : [];
        $values = is_array($values) ? $values : [];
        $id = (string) $this->config['id'];
        $prefix = 'pressundo_metabox_' . $id;
        $theme = sanitize_key((string) $this->schema['ui_theme']) ?: 'air';

        wp_nonce_field('pressundo_metabox_' . $id, $prefix . '_nonce');
        echo '<input type="hidden" name="' . esc_attr($prefix) . '_present" value="1">';
        echo '<input type="hidden" name="' . esc_attr($prefix) . '_revision" value="' . esc_attr(SecurityPolicy::revision($values)) . '">';
        echo '<div class="pressundo-modern-metabox pressundo-ui--' . esc_attr($theme) . '">';
        $this->renderNotice($postId);
        $rendered = $this->renderer->render($this->schema, $values, false, $prefix . '_values');
        echo $rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SchemaFormRenderer escapes field output by type.
        echo '</div>';
    }

    public function save(int $postId, mixed $post = null): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }
        if (! current_user_can('edit_post', $postId)) {
            return;
        }
        $postType = function_exists('get_post_type') ? (string) get_post_type($postId) : '';
        if ($postType !== '' && ! in_array($postType, (array) $this->config['post_types'], true)) {
            return;
        }

        $id = (string) $this->config['id'];
        $prefix = 'pressundo_metabox_' . $id;
        if (! isset($_POST[$prefix . '_present'])) {
            return;
        }

        $nonce = isset($_POST[$prefix . '_nonce']) ? sanitize_text_field(wp_unslash($_POST[$prefix . '_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'pressundo_metabox_' . $id)) {
            return;
        }

        $current = get_post_meta($postId, (string) $this->config['storage_key'], true);
        $current = is_array($current) ? $current : [];
        $revision = isset($_POST[$prefix . '_revision'])
            ? sanitize_text_field(wp_unslash($_POST[$prefix . '_revision']))
            : '';
        if ($revision !== '' && ! SecurityPolicy::revisionsMatch($revision, $current)) {
            $this->setNotice($postId, __('Settings changed in another session. Reload the page before saving to avoid overwriting newer data.', 'pressundo-framework'));
            do_action('pressundo_metabox_save_conflict', $id, $postId, $current);
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every field is schema-sanitized below.
        $raw = isset($_POST[$prefix . '_values']) ? wp_unslash($_POST[$prefix . '_values']) : [];
        $raw = is_array($raw) ? $raw : [];
        $payload = [];
        $errors = [];

        foreach ($this->fields() as $field) {
            $name = sanitize_key((string) ($field['name'] ?? ''));
            if ($name === '' || in_array((string) ($field['type'] ?? ''), ['html', 'notice', 'heading', 'divider'], true)) {
                continue;
            }
            $value = $this->cleanRawValue($field, $raw[$name] ?? ($field['default'] ?? null));
            $result = $this->processor->validate($field, $value);
            if (! $result->isValid()) {
                foreach ($result->errors() as $error) {
                    $errors[] = $name . ': ' . $error;
                }
                continue;
            }
            $payload[$name] = $result->value();
        }

        if ($errors !== []) {
            $message = __('Some fields are invalid. Nothing was saved.', 'pressundo-framework');
            if ($errors !== []) {
                $message .= ' ' . implode(' ', array_slice($errors, 0, 3));
            }
            $this->setNotice($postId, $message);
            do_action('pressundo_metabox_validation_failed', $id, $postId, $errors, $raw);
            return;
        }

        update_post_meta($postId, (string) $this->config['storage_key'], $payload);
        do_action('pressundo_metabox_saved', $id, $postId, $payload);
    }

    /** @return array<string,mixed> */
    public function schema(): array
    {
        return $this->schema;
    }

    private function noticeKey(int $postId): string
    {
        $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        return 'pressundo_metabox_notice_' . md5((string) $this->config['id'] . '|' . $postId . '|' . $userId);
    }

    private function setNotice(int $postId, string $message): void
    {
        if (function_exists('set_transient')) {
            set_transient($this->noticeKey($postId), SecurityPolicy::text($message), 60);
        }
    }

    private function renderNotice(int $postId): void
    {
        if (! function_exists('get_transient') || ! function_exists('delete_transient')) {
            return;
        }
        $key = $this->noticeKey($postId);
        $message = get_transient($key);
        if (! is_string($message) || $message === '') {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error inline"><p>' . esc_html($message) . '</p></div>';
    }

    /** @return list<array<string,mixed>> */
    private function fields(): array
    {
        if ((array) ($this->schema['sections'] ?? []) !== []) {
            $fields = [];
            foreach ((array) $this->schema['sections'] as $section) {
                foreach ((array) ($section['fields'] ?? []) as $field) {
                    if (is_array($field)) {
                        $fields[] = $field;
                    }
                }
            }
            return $fields;
        }
        return array_values(array_filter((array) ($this->schema['fields'] ?? []), 'is_array'));
    }

    /** @param array<string,mixed> $field */
    private function cleanRawValue(array $field, mixed $value): mixed
    {
        if (($field['type'] ?? '') !== 'group' || ! is_array($value)) {
            return $value;
        }
        $children = array_values(array_filter((array) ($field['fields'] ?? []), 'is_array'));
        if (! empty($field['repeating'])) {
            unset($value['__pressundo_repeater_present']);
            $rows = [];
            foreach ($value as $row) {
                if (! is_array($row)) {
                    continue;
                }
                unset($row['__pressundo_row_key']);
                foreach ($children as $child) {
                    $name = sanitize_key((string) ($child['name'] ?? ''));
                    if ($name !== '' && array_key_exists($name, $row)) {
                        $row[$name] = $this->cleanRawValue($child, $row[$name]);
                    }
                }
                $rows[] = $row;
            }
            return $rows;
        }
        foreach ($children as $child) {
            $name = sanitize_key((string) ($child['name'] ?? ''));
            if ($name !== '' && array_key_exists($name, $value)) {
                $value[$name] = $this->cleanRawValue($child, $value[$name]);
            }
        }
        return $value;
    }
}
