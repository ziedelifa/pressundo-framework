<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use Exception;
use Pressundo\Framework\Bootstrap;
use Pressundo\Framework\Admin\AssetManager;
use Pressundo\Framework\Renderer\SchemaFormRenderer;
use Pressundo\Framework\Security\SecurityPolicy;

final class MetaboxAdapter
{
    /** @var array<string,self> */
    public static array $pool = [];

    public string $id = '';
    public string $title = 'Custom Meta';
    public mixed $template = null;
    /** @var list<string> */
    public array $types = ['post'];
    public string $context = 'normal';
    public string $priority = 'high';
    public string $mode = 'array';
    public string $prefix = '';
    public bool $hide_editor = false;
    public bool $is_dev_mode = false;

    /** @var array<string,mixed> */
    private array $schema = [];
    /** @var array<string,mixed> */
    private array $report = [];
    /** @var array<string,mixed> */
    private array $meta = [];
    private ?int $currentPostId = null;
    private ?string $currentField = null;
    private mixed $currentHint = null;

    public function __construct($arr)
    {
        if (! is_array($arr)) {
            if (! is_string($arr) || ! is_readable($arr)) {
                throw new Exception('Metabox configuration Array/File is required', 1);
            }
            /** @var LegacyTemplateLoader $configurationLoader */
            $configurationLoader = Bootstrap::instance()->container()->get(LegacyTemplateLoader::class);
            $arr = $configurationLoader->load($arr);
        }

        if (empty($arr['id'])) {
            throw new Exception('Metabox ID is required', 1);
        }
        if (! array_key_exists('template', $arr)) {
            throw new Exception('Metabox Template Array/File is required', 1);
        }

        $this->id = (string) $arr['id'];
        $this->title = (string) ($arr['title'] ?? 'Custom Meta');
        $this->template = $arr['template'];
        $types = $arr['types'] ?? ['post'];
        $this->types = is_array($types) ? array_values(array_map('strval', $types)) : [(string) $types];
        $this->context = (string) ($arr['context'] ?? 'normal');
        $this->priority = (string) ($arr['priority'] ?? 'high');
        $this->mode = (string) ($arr['mode'] ?? (defined('WPALCHEMY_MODE_ARRAY') ? WPALCHEMY_MODE_ARRAY : 'array'));
        $this->prefix = (string) ($arr['prefix'] ?? '');
        $this->hide_editor = ! empty($arr['hide_editor']);
        $this->is_dev_mode = ! empty($arr['is_dev_mode']);

        $container = Bootstrap::instance()->container();
        /** @var LegacyTemplateLoader $loader */
        $loader = $container->get(LegacyTemplateLoader::class);
        /** @var TemplateConverter $converter */
        $converter = $container->get(TemplateConverter::class);
        $legacyTemplate = $loader->load($this->template);
        $converted = $converter->convert($legacyTemplate, $this->id);
        $this->schema = (array) $converted['schema'];
        $this->schema['title'] = $this->title;
        $this->report = (array) $converted['report'];

        self::$pool[$this->id] = $this;

        if (function_exists('add_action')) {
            add_action('add_meta_boxes', [$this, 'register_meta_box']);
            add_action('save_post', [$this, 'save_post'], 10, 2);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        }
    }

    /** @return array<string,self> */
    public static function get_pool(): array
    {
        return self::$pool;
    }


    public function register_fields(): void
    {
        // Original Vafpress registered field assets here. Pressundo's asset
        // manager discovers requirements from the converted schema instead.
        $this->enqueue_assets('');
    }

    public static function _is_post(): bool
    {
        return self::_get_current_post_type() !== 'page';
    }

    public static function _is_page(): bool
    {
        return self::_get_current_post_type() === 'page';
    }

    public static function _is_post_or_page(): bool
    {
        $type = self::_get_current_post_type();
        return $type === 'post' || $type === 'page';
    }

    public static function _get_current_post_type(): string
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if (is_object($screen) && ! empty($screen->post_type)) {
                return (string) $screen->post_type;
            }
        }
        global $post;
        return is_object($post) && ! empty($post->post_type) ? (string) $post->post_type : '';
    }

    public static function _get_post_id(): int
    {
        global $post;
        return is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
    }

    public static function pool_can_output(): bool
    {
        foreach (self::$pool as $box) {
            if ($box->can_output()) {
                return true;
            }
        }
        return false;
    }

    public static function pool_supports_editor(): bool
    {
        foreach (self::$pool as $box) {
            if ($box->supports_editor()) {
                return true;
            }
        }
        return false;
    }

    public function can_output(): bool
    {
        if (! function_exists('get_current_screen')) {
            return true;
        }
        $screen = get_current_screen();
        if (! is_object($screen) || empty($screen->post_type)) {
            return true;
        }
        return in_array((string) $screen->post_type, $this->types, true);
    }

    public function supports_editor(): bool
    {
        if (! function_exists('post_type_supports')) {
            return true;
        }
        $postType = $this->types[0] ?? 'post';
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if (is_object($screen) && ! empty($screen->post_type)) {
                $postType = (string) $screen->post_type;
            }
        }
        return post_type_supports($postType, 'editor');
    }

    public function register_meta_box(): void
    {
        if (! function_exists('add_meta_box')) {
            return;
        }
        foreach ($this->types as $type) {
            add_meta_box($this->id . '_metabox', $this->title, [$this, 'render'], $type, $this->context, $this->priority);
        }
    }

    public function enqueue_assets(string $hook = ''): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php', ''], true) || ! $this->can_output()) {
            return;
        }
        /** @var AssetManager $assets */
        $assets = Bootstrap::instance()->container()->get(AssetManager::class);
        $assets->enqueueForSchema($this->schema);
    }

    public function render($post): void
    {
        $postId = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
        $values = $this->the_meta($postId);
        /** @var SchemaFormRenderer $renderer */
        $renderer = Bootstrap::instance()->container()->get(SchemaFormRenderer::class);

        if ($this->is_dev_mode) {
            echo '<p class="notice notice-warning inline">' . esc_html__('Vafpress development mode is active. Values are not written to the database.', 'pressundo-framework') . '</p>';
        }
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('pressundo_vp_metabox_' . $this->id, $this->id . '_nonce');
        }
        echo '<input type="hidden" name="pressundo_vp_metabox_present[' . esc_attr($this->id) . ']" value="1">';
        echo '<input type="hidden" name="pressundo_vp_metabox_revision[' . esc_attr($this->id) . ']" value="' . esc_attr(SecurityPolicy::revision($values)) . '">';
        echo $renderer->render($this->schema, $values, false, 'pressundo_vp_meta[' . $this->id . ']', true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function save_post(int $postId, $post = null): void
    {
        if (! isset($_POST['pressundo_vp_metabox_present'][$this->id])) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }
        if (function_exists('current_user_can') && ! current_user_can('edit_post', $postId)) {
            return;
        }
        if (function_exists('check_admin_referer')) {
            check_admin_referer('pressundo_vp_metabox_' . $this->id, $this->id . '_nonce');
        }
        if ($this->is_dev_mode) {
            return;
        }

        $current = $this->readStorage($postId);
        $revision = isset($_POST['pressundo_vp_metabox_revision'][$this->id])
            ? sanitize_text_field(wp_unslash($_POST['pressundo_vp_metabox_revision'][$this->id]))
            : '';
        if ((string) $revision !== '' && ! SecurityPolicy::revisionsMatch($revision, $current)) {
            if (function_exists('do_action')) {
                do_action('pressundo_vp_metabox_save_conflict', $this->id, $postId, $current);
            }
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Schema-aware sanitization is performed by LegacyRuntimeSaveService below.
        $submitted = isset($_POST['pressundo_vp_meta'][$this->id]) ? wp_unslash($_POST['pressundo_vp_meta'][$this->id]) : [];
        if (! is_array($submitted)) {
            $submitted = [];
        }

        /** @var LegacyRuntimeSaveService $save */
        $save = Bootstrap::instance()->container()->get(LegacyRuntimeSaveService::class);
        $result = $save->sanitize($this->schema, $current, $submitted);
        if (empty($result['submission_gate_passed']) || ! is_array($result['payload'] ?? null)) {
            return;
        }

        $payload = (array) $result['payload'];
        $this->writeStorage($postId, $payload);
        $this->meta = $payload;
        $this->currentPostId = $postId;

        if (function_exists('do_action')) {
            do_action('pressundo_vafpress_metabox_saved', $this->id, $postId, $payload);
        }
    }

    /** @return array<string,mixed> */
    public function the_meta($postId = null): array
    {
        if (! is_numeric($postId)) {
            global $post;
            $postId = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
        }
        $postId = (int) $postId;
        if ($postId < 1) {
            return [];
        }
        if ($this->currentPostId === $postId) {
            return $this->meta;
        }
        $this->currentPostId = $postId;
        $this->meta = $this->readStorage($postId);
        return $this->meta;
    }

    /** @return array<string,mixed> */
    public function _meta($postId = null, $internal = false): array
    {
        return $this->the_meta($postId);
    }

    public function the_id(): void { echo esc_attr($this->get_the_id()); }
    public function get_the_id(): string { return $this->id; }

    public function the_field($name, $hint = null): void
    {
        $this->currentField = (string) $name;
        $this->currentHint = $hint;
    }

    public function have_value($name = null): bool { return (bool) $this->get_the_value($name); }
    public function the_value($name = null): void
    {
        $value = $this->get_the_value($name);
        if (is_scalar($value)) { echo esc_html((string) $value); }
    }

    public function get_the_value($key = null, $collection = false): mixed
    {
        $meta = $this->the_meta();
        $key = $key === null ? $this->currentField : $key;
        if ($key === null || $key === '') { return $collection ? $meta : null; }
        $segments = is_array($key) ? $key : explode('.', (string) $key);
        $value = $meta;
        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists((string) $segment, $value)) {
                return null;
            }
            $value = $value[(string) $segment];
        }
        return $value;
    }

    public function the_name($name = null): void { echo esc_attr($this->get_the_name($name)); }
    public function get_the_name($name = null): string
    {
        $name = $name === null ? $this->currentField : (string) $name;
        if ($this->mode === 'extract') { return $this->prefix . (string) $name; }
        return $this->id . '[' . (string) $name . ']';
    }

    public function is_value($name, $value = null): bool
    {
        if ($value === null) { $value = $name; $current = $this->get_the_value(); }
        else { $current = $this->get_the_value($name); }
        return $current == $value;
    }

    public function is_selected($name, $value = null): bool
    {
        if ($value === null) { $value = $name; $current = $this->get_the_value(); }
        else { $current = $this->get_the_value($name); }
        return is_array($current) ? in_array($value, $current) : $current == $value;
    }

    public function get_the_checkbox_state($name, $value = null): ?string { return $this->is_selected($name, $value) ? ' checked="checked"' : null; }
    public function the_checkbox_state($name, $value = null): void
    {
        if ($this->is_selected($name, $value)) {
            echo ' checked="checked"';
        }
    }
    public function get_the_radio_state($name, $value = null): ?string { return $this->get_the_checkbox_state($name, $value); }
    public function the_radio_state($name, $value = null): void
    {
        if ($this->is_selected($name, $value)) {
            echo ' checked="checked"';
        }
    }
    public function get_the_select_state($name, $value = null): ?string { return $this->is_selected($name, $value) ? ' selected="selected"' : null; }
    public function the_select_state($name, $value = null): void
    {
        if ($this->is_selected($name, $value)) {
            echo ' selected="selected"';
        }
    }

    /** @return array<string,mixed> */
    public function get_schema(): array { return $this->schema; }
    /** @return array<string,mixed> */
    public function get_migration_report(): array { return $this->report; }

    /** @return list<string> */
    public function get_field_types(): array
    {
        $types = [];
        foreach ((array) ($this->report['fields'] ?? []) as $field) {
            if (is_array($field) && isset($field['legacy_type'])) {
                $types[] = (string) $field['legacy_type'];
            }
        }
        return array_values(array_unique($types));
    }

    /** @return array<string,mixed> */
    private function readStorage(int $postId): array
    {
        if (! function_exists('get_post_meta')) {
            return [];
        }

        if ($this->mode === 'extract') {
            $fields = get_post_meta($postId, $this->id . '_fields', true);
            $values = [];
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    $field = (string) $field;
                    $name = $this->prefix !== '' && str_starts_with($field, $this->prefix)
                        ? substr($field, strlen($this->prefix))
                        : $field;
                    $values[$name] = get_post_meta($postId, $field, true);
                }
            }
            return $values;
        }

        $value = get_post_meta($postId, $this->id, true);
        return is_array($value) ? $value : [];
    }

    /** @param array<string,mixed> $payload */
    private function writeStorage(int $postId, array $payload): void
    {
        if ($this->mode === 'extract') {
            $currentFields = function_exists('get_post_meta') ? get_post_meta($postId, $this->id . '_fields', true) : [];
            $newFields = [];
            foreach ($payload as $name => $value) {
                $field = $this->prefix . (string) $name;
                $newFields[] = $field;
                if ($value === null) {
                    delete_post_meta($postId, $field);
                } else {
                    update_post_meta($postId, $field, $value);
                }
            }
            foreach ((array) $currentFields as $field) {
                if (! in_array((string) $field, $newFields, true)) {
                    delete_post_meta($postId, (string) $field);
                }
            }
            delete_post_meta($postId, $this->id . '_fields');
            if ($newFields !== []) {
                add_post_meta($postId, $this->id . '_fields', $newFields, true);
            }
            delete_post_meta($postId, $this->id);
            return;
        }

        update_post_meta($postId, $this->id, $payload);
        $fields = get_post_meta($postId, $this->id . '_fields', true);
        foreach ((array) $fields as $field) {
            delete_post_meta($postId, (string) $field);
        }
        delete_post_meta($postId, $this->id . '_fields');
    }
}
