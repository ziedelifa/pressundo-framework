<?php

declare(strict_types=1);

namespace Pressundo\Framework\Admin;

use Pressundo\Framework\Api\FieldValueProcessor;
use Pressundo\Framework\Renderer\SchemaFormRenderer;
use Pressundo\Framework\Security\SecurityPolicy;

final class OptionsPage
{
    private string $hookSuffix = '';
    /** @var array<string,mixed> */
    private array $config;
    /** @var array<string,mixed> */
    private array $schema;
    /** @var list<string> */
    private array $errors = [];

    /** @param array<string,mixed> $config */
    public function __construct(
        array $config,
        private readonly AssetManager $assets,
        private readonly SchemaFormRenderer $renderer,
        private readonly FieldValueProcessor $processor
    ) {
        $id = sanitize_key((string) ($config['id'] ?? 'pressundo-options')) ?: 'pressundo-options';
        $title = (string) ($config['title'] ?? 'Pressundo Options');
        $this->config = array_replace([
            'id' => $id,
            'title' => $title,
            'menu_title' => $title,
            'parent_slug' => 'themes.php',
            'capability' => 'edit_theme_options',
            'storage_key' => $id,
            'ui_theme' => 'air',
            'sections' => [],
            'priority' => 10,
        ], $config);
        $this->config['id'] = $id;
        $this->schema = [
            'id' => $id,
            'kind' => 'options',
            'title' => $title,
            'ui_theme' => sanitize_key((string) $this->config['ui_theme']) ?: 'air',
            'sections' => array_values(array_filter((array) $this->config['sections'], 'is_array')),
        ];
        add_action('admin_menu', [$this, 'register'], (int) $this->config['priority']);
    }

    public function register(): void
    {
        $parent = (string) $this->config['parent_slug'];
        if ($parent === '') {
            $hook = add_menu_page(
                (string) $this->config['title'],
                (string) $this->config['menu_title'],
                (string) $this->config['capability'],
                (string) $this->config['id'],
                [$this, 'render'],
                (string) ($this->config['icon_url'] ?? 'dashicons-admin-generic'),
                $this->config['position'] ?? null
            );
        } else {
            $hook = add_submenu_page(
                $parent,
                (string) $this->config['title'],
                (string) $this->config['menu_title'],
                (string) $this->config['capability'],
                (string) $this->config['id'],
                [$this, 'render']
            );
        }
        $this->hookSuffix = is_string($hook) ? $hook : '';
        if ($this->hookSuffix !== '') {
            add_action('load-' . $this->hookSuffix, [$this, 'setup']);
        }
    }

    public function setup(): void
    {
        $this->assets->enqueueForSchema($this->schema);
        $this->handleSave();
    }

    public function render(): void
    {
        if (! current_user_can((string) $this->config['capability'])) {
            wp_die(esc_html__('You are not allowed to access this page.', 'pressundo-framework'));
        }
        $values = get_option((string) $this->config['storage_key'], []);
        $values = is_array($values) ? $values : [];
        $theme = sanitize_key((string) $this->schema['ui_theme']) ?: 'air';

        echo '<div class="wrap pressundo-options-page pressundo-options-page--' . esc_attr($theme) . '">';
        echo '<div class="pressundo-options-header"><div><span class="pressundo-options-eyebrow">Pressundo Framework</span><h1>' . esc_html((string) $this->config['title']) . '</h1>';
        if (! empty($this->config['description'])) { echo '<p>' . esc_html((string) $this->config['description']) . '</p>'; }
        echo '</div><span class="pressundo-options-skin">' . esc_html(ucfirst($theme)) . '</span></div>';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by Pressundo after a verified save.
        if (isset($_GET['pressundo_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'pressundo-framework') . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by Pressundo after a verified save.
        if (isset($_GET['pressundo_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Some fields are invalid. Nothing was saved.', 'pressundo-framework') . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by Pressundo after a verified save.
        if (isset($_GET['pressundo_conflict'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Settings changed in another session. Reload the page before saving to avoid overwriting newer data.', 'pressundo-framework') . '</p></div>';
        }

        echo '<form method="post" class="pressundo-options-form">';
        wp_nonce_field('pressundo_options_' . (string) $this->config['id'], 'pressundo_options_nonce');
        echo '<input type="hidden" name="pressundo_options_action" value="save">';
        echo '<input type="hidden" name="pressundo_options_revision" value="' . esc_attr(SecurityPolicy::revision($values)) . '">';
        echo '<div class="pressundo-options-layout"><nav class="pressundo-options-nav" aria-label="' . esc_attr__('Settings sections', 'pressundo-framework') . '">';
        foreach ((array) $this->schema['sections'] as $index => $section) {
            $sid = sanitize_key((string) ($section['id'] ?? 'section-' . $index));
            $stitle = (string) ($section['title'] ?? ucwords(str_replace(['_','-'], ' ', $sid)));
            echo '<a href="#pressundo-section-' . esc_attr($sid) . '"' . ($index === 0 ? ' class="is-active"' : '') . '><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>' . esc_html($stitle) . '</a>';
        }
        echo '</nav><main class="pressundo-options-main">';
        echo $this->renderer->render($this->schema, $values, false, 'pressundo_values'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="pressundo-options-savebar"><div><strong>' . esc_html__('Ready to save', 'pressundo-framework') . '</strong><span>' . esc_html__('Changes are validated before they reach WordPress storage.', 'pressundo-framework') . '</span></div>';
        submit_button(__('Save changes', 'pressundo-framework'), 'primary', 'submit', false);
        echo '</div></main></div></form></div>';
    }

    /** @return array<string,mixed> */
    public function schema(): array { return $this->schema; }

    private function handleSave(): void
    {
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';
        if ($requestMethod !== 'POST' || ! isset($_POST['pressundo_options_action'])) {
            return;
        }
        if (! current_user_can((string) $this->config['capability'])) {
            return;
        }
        check_admin_referer('pressundo_options_' . (string) $this->config['id'], 'pressundo_options_nonce');
        $action = sanitize_key(wp_unslash($_POST['pressundo_options_action']));
        if ($action !== 'save') {
            return;
        }
        $current = get_option((string) $this->config['storage_key'], []);
        $current = is_array($current) ? $current : [];
        $revision = isset($_POST['pressundo_options_revision']) ? sanitize_text_field(wp_unslash($_POST['pressundo_options_revision'])) : '';
        if ($revision !== '' && ! SecurityPolicy::revisionsMatch($revision, $current)) {
            $url = add_query_arg('pressundo_conflict', '1', menu_page_url((string) $this->config['id'], false));
            wp_safe_redirect($url);
            exit;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized and validated by FieldValueProcessor below.
        $raw = isset($_POST['pressundo_values']) ? wp_unslash($_POST['pressundo_values']) : [];
        $raw = is_array($raw) ? $raw : [];
        $payload = [];
        $errors = [];
        foreach ((array) $this->schema['sections'] as $section) {
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field)) { continue; }
                $name = sanitize_key((string) ($field['name'] ?? ''));
                if ($name === '' || in_array((string) ($field['type'] ?? ''), ['html', 'notice', 'heading', 'divider'], true)) { continue; }
                $value = $this->cleanRawValue($field, $raw[$name] ?? ($field['default'] ?? null));
                $result = $this->processor->validate($field, $value);
                if (! $result->isValid()) {
                    foreach ($result->errors() as $error) { $errors[] = $name . ': ' . $error; }
                    continue;
                }
                $payload[$name] = $result->value();
            }
        }
        $this->errors = $errors;
        $ok = $errors === [];
        if ($ok) { update_option((string) $this->config['storage_key'], $payload, false); }
        $url = add_query_arg($ok ? 'pressundo_saved' : 'pressundo_error', '1', menu_page_url((string) $this->config['id'], false));
        wp_safe_redirect($url);
        exit;
    }

    /** @param array<string,mixed> $field */
    private function cleanRawValue(array $field, mixed $value): mixed
    {
        if (($field['type'] ?? '') !== 'group' || ! is_array($value)) { return $value; }
        $children = array_values(array_filter((array) ($field['fields'] ?? []), 'is_array'));
        if (! empty($field['repeating'])) {
            unset($value['__pressundo_repeater_present']);
            $rows = [];
            foreach ($value as $row) {
                if (! is_array($row)) { continue; }
                unset($row['__pressundo_row_key']);
                foreach ($children as $child) {
                    $name = sanitize_key((string) ($child['name'] ?? ''));
                    if ($name !== '' && array_key_exists($name, $row)) { $row[$name] = $this->cleanRawValue($child, $row[$name]); }
                }
                $rows[] = $row;
            }
            return $rows;
        }
        foreach ($children as $child) {
            $name = sanitize_key((string) ($child['name'] ?? ''));
            if ($name !== '' && array_key_exists($name, $value)) { $value[$name] = $this->cleanRawValue($child, $value[$name]); }
        }
        return $value;
    }
}
