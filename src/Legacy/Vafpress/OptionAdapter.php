<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use Exception;
use Pressundo\Framework\Bootstrap;
use Pressundo\Framework\Admin\AssetManager;
use Pressundo\Framework\Renderer\SchemaFormRenderer;
use Pressundo\Framework\Security\SecurityPolicy;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exact vp_* hooks are part of the Vafpress compatibility contract.
use RuntimeException;

final class OptionAdapter
{
    /** @var array<string,self> */
    public static array $pool = [];

    private string $optionKey = '';
    private string $pageSlug = '';
    private mixed $template = null;
    private bool $devMode = false;
    private bool $useUtilMenu = true;
    private bool $useAutoGroupNaming = true;
    private string $role = 'edit_theme_options';
    private mixed $menuPage = 'themes.php';
    private string $pageTitle = 'Vafpress Options';
    private string $menuLabel = 'Vafpress Options';
    private string $layout = 'fixed';
    private string $uiTheme = 'air';
    private string $hookSuffix = '';
    /** @var array<string,mixed> */
    private array $schema = [];
    /** @var array<string,mixed> */
    private array $report = [];
    /** @var array<string,mixed> */
    private array $options = [];
    private mixed $optionsSet = null;

    public function __construct(array $configs)
    {
        $configs = array_merge([
            'is_dev_mode' => false,
            'use_auto_group_naming' => true,
            'use_util_menu' => true,
            'minimum_role' => 'edit_theme_options',
            'menu_page' => 'themes.php',
            'layout' => 'fixed',
            'page_title' => function_exists('__') ? __('Vafpress Options', 'pressundo-framework') : 'Vafpress Options',
            'menu_label' => function_exists('__') ? __('Vafpress Options', 'pressundo-framework') : 'Vafpress Options',
            'priority' => 10,
            'ui_theme' => 'air',
        ], $configs);

        $keyForFilter = (string) ($configs['option_key'] ?? '');
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('vp_option_configuration_array', $configs, $keyForFilter);
            if (is_array($filtered)) {
                $configs = $filtered;
            }
            if ($keyForFilter !== '') {
                $filtered = apply_filters('vp_option_configuration_array-' . $keyForFilter, $configs);
                if (is_array($filtered)) {
                    $configs = $filtered;
                }
            }
        }

        if (empty($configs['option_key'])) {
            throw new Exception('Option Key is required', 1);
        }
        if (! array_key_exists('template', $configs)) {
            throw new Exception('Template Array/File is required', 1);
        }
        if (empty($configs['page_slug'])) {
            throw new Exception('Page Slug is required', 1);
        }

        $this->optionKey = (string) $configs['option_key'];
        $this->pageSlug = (string) $configs['page_slug'];
        $this->template = $configs['template'];
        $this->menuPage = $configs['menu_page'] ?? 'themes.php';
        $this->devMode = ! empty($configs['is_dev_mode']);
        $this->useUtilMenu = ! array_key_exists('use_util_menu', $configs) || (bool) $configs['use_util_menu'];
        $this->useAutoGroupNaming = ! array_key_exists('use_auto_group_naming', $configs) || (bool) $configs['use_auto_group_naming'];
        $this->role = (string) ($configs['minimum_role'] ?? 'edit_theme_options');
        $this->pageTitle = (string) ($configs['page_title'] ?? 'Vafpress Options');
        $this->menuLabel = (string) ($configs['menu_label'] ?? $this->pageTitle);
        $this->layout = (string) ($configs['layout'] ?? 'fixed');
        $this->uiTheme = sanitize_key((string) ($configs['ui_theme'] ?? 'air')) ?: 'air';
        if (! in_array($this->uiTheme, ['air', 'slate', 'compact'], true)) { $this->uiTheme = 'air'; }

        $container = Bootstrap::instance()->container();
        /** @var LegacyTemplateLoader $loader */
        $loader = $container->get(LegacyTemplateLoader::class);
        /** @var TemplateConverter $converter */
        $converter = $container->get(TemplateConverter::class);

        $legacyTemplate = $loader->load($this->template);
        $converted = $converter->convert($legacyTemplate, $this->optionKey);
        $this->schema = (array) $converted['schema'];
        $this->schema['title'] = $this->pageTitle;
        $this->schema['ui_theme'] = $this->uiTheme;
        $modernSections = array_values(array_filter((array) ($configs['pressundo_sections'] ?? []), 'is_array'));
        if ($modernSections !== []) {
            $this->schema['sections'] = array_merge((array) ($this->schema['sections'] ?? []), $modernSections);
        }
        $this->report = (array) $converted['report'];

        self::$pool[$this->optionKey] = $this;
        $this->initializeOptions();

        if (function_exists('add_action')) {
            add_action('admin_menu', [$this, 'register_menu_page'], (int) ($configs['priority'] ?? 10));
        }
    }

    /** @return array<string,self> */
    public static function get_pool(): array
    {
        return self::$pool;
    }

    public function register_menu_page(): void
    {
        if (! function_exists('add_submenu_page')) {
            return;
        }

        if (is_array($this->menuPage)) {
            $hook = add_menu_page(
                $this->pageTitle,
                $this->menuLabel,
                $this->role,
                $this->pageSlug,
                [$this, 'option_page_display'],
                (string) ($this->menuPage['icon_url'] ?? ''),
                $this->menuPage['position'] ?? null
            );
        } else {
            $hook = add_submenu_page(
                (string) $this->menuPage,
                $this->pageTitle,
                $this->menuLabel,
                $this->role,
                $this->pageSlug,
                [$this, 'option_page_display']
            );
        }

        $this->hookSuffix = is_string($hook) ? $hook : '';
        if ($this->hookSuffix !== '') {
            add_action('load-' . $this->hookSuffix, [$this, 'setup']);
        }
    }

    public function setup(): void
    {
        $this->init_options_set();
        $this->init_options();
        $this->enqueue_scripts_and_styles();
        $this->handleSave();
        if ($this->devMode && function_exists('add_action')) {
            add_action('admin_notices', [$this, 'dev_mode_notice']);
        }
    }

    public function enqueue_scripts_and_styles(): void
    {
        /** @var AssetManager $assets */
        $assets = Bootstrap::instance()->container()->get(AssetManager::class);
        $assets->enqueueForSchema($this->schema);
    }

    public function option_page_display(): void
    {
        if (function_exists('current_user_can') && ! current_user_can($this->role)) {
            wp_die(esc_html__('You are not allowed to access this page.', 'pressundo-framework'));
        }

        $this->init_options_from_db();
        /** @var SchemaFormRenderer $renderer */
        $renderer = Bootstrap::instance()->container()->get(SchemaFormRenderer::class);

        echo '<div class="wrap pressundo-options-page pressundo-options-page--' . esc_attr($this->uiTheme) . ' pressundo-vafpress-compat">';
        echo '<div class="pressundo-options-header"><div><span class="pressundo-options-eyebrow">Pressundo Framework</span><h1>' . esc_html($this->pageTitle) . '</h1><p>' . esc_html__('Vafpress-compatible and modern Pressundo fields share the same renderer and storage page.', 'pressundo-framework') . '</p></div><span class="pressundo-options-skin">' . esc_html(ucfirst($this->uiTheme)) . '</span></div>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by Pressundo after a verified save.
        if (isset($_GET['pressundo_vp_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'pressundo-framework') . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by Pressundo after a verified save.
        if (isset($_GET['pressundo_vp_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Some legacy fields could not be saved safely. Existing values were preserved.', 'pressundo-framework') . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by Pressundo after a verified save.
        if (isset($_GET['pressundo_vp_conflict'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Legacy settings changed in another session. Reload before saving to avoid overwriting newer data.', 'pressundo-framework') . '</p></div>';
        }
        if ($this->devMode) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Vafpress development mode is active. Values are not written to the database.', 'pressundo-framework') . '</p></div>';
        }

        echo '<form method="post" class="pressundo-options-form">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('pressundo_vp_option_' . $this->optionKey, 'pressundo_vp_nonce');
        }
        echo '<input type="hidden" name="pressundo_vp_action" value="save">';
        echo '<input type="hidden" name="pressundo_vp_revision" value="' . esc_attr(SecurityPolicy::revision($this->options)) . '">';
        echo '<div class="pressundo-options-layout"><nav class="pressundo-options-nav" aria-label="' . esc_attr__('Settings sections', 'pressundo-framework') . '">';
        foreach ((array) ($this->schema['sections'] ?? []) as $index => $section) {
            if (! is_array($section)) { continue; }
            $sid = sanitize_key((string) ($section['id'] ?? 'section-' . $index));
            $stitle = (string) ($section['title'] ?? ucwords(str_replace(['_','-'], ' ', $sid)));
            echo '<a href="#pressundo-section-' . esc_attr($sid) . '"' . ($index === 0 ? ' class="is-active"' : '') . '><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>' . esc_html($stitle) . '</a>';
        }
        echo '</nav><main class="pressundo-options-main">';
        echo $renderer->render($this->schema, $this->options, false, 'pressundo_legacy_values', true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="pressundo-options-savebar"><div><strong>' . esc_html__('Legacy data preserved', 'pressundo-framework') . '</strong><span>' . esc_html__('Pressundo writes the original option key and data shape.', 'pressundo-framework') . '</span></div>';
        if (function_exists('submit_button')) { submit_button(__('Save changes', 'pressundo-framework'), 'primary', 'submit', false); }
        else { echo '<button type="submit">Save changes</button>'; }
        echo '</div></main></div></form></div>';
    }

    public function init_options_from_db(): void
    {
        $value = function_exists('get_option') ? get_option($this->optionKey, []) : [];
        $this->options = is_array($value) ? $value : [];
        if ($this->optionsSet instanceof OptionSetAdapter) {
            $this->optionsSet->set_values($this->options);
        }
    }

    public function dev_mode_notice(): void
    {
        $message = function_exists('__')
            ? __("Development Mode is Active, options' values won't be saved into database.", 'pressundo-framework')
            : "Development Mode is Active, options' values won't be saved into database.";
        if (function_exists('wp_admin_notice')) {
            wp_admin_notice($message, ['type' => 'warning']);
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
    }

    public function init_options_set(): void
    {
        if ($this->optionsSet !== null) {
            return;
        }
        $this->optionsSet = new OptionSetAdapter($this->schema, $this->options, $this->optionKey);
    }

    public function init_options(): void
    {
        $this->init_options_set();
        $defaults = Bootstrap::instance()->container()->get(LegacyDefaults::class)->fromSchema($this->schema);
        $db = function_exists('get_option') ? get_option($this->optionKey, []) : [];
        $db = is_array($db) ? $db : [];
        $this->options = $this->devMode ? $defaults : array_replace($defaults, $db);
        if ($this->optionsSet instanceof OptionSetAdapter) {
            $this->optionsSet->setup($this->options);
        }
    }

    /**
     * Historical programmatic save API. It remains available, but Pressundo
     * uses its safe modern request path for admin form submissions.
     *
     * @return array{status:bool,code:int,message:string}
     */
    public function save_and_reinit(): array
    {
        $this->init_options_set();
        if ($this->optionsSet instanceof OptionSetAdapter) {
            $this->optionsSet->set_values($this->options);
            if ($this->devMode) {
                return ['status' => true, 'code' => OptionSetAdapter::SAVE_NOCHANGES, 'message' => 'No changes made'];
            }
            $result = $this->optionsSet->save($this->optionKey);
            $this->init_options_from_db();
            if (function_exists('do_action')) {
                do_action('vp_option_save_and_reinit', $this->options, $result['status'], $this->optionKey);
                do_action('vp_option_save_and_reinit-' . $this->optionKey, $this->options, $result['status']);
            }
            return $result;
        }
        return ['status' => false, 'code' => OptionSetAdapter::SAVE_FAILED, 'message' => 'Saving failed'];
    }

    public function initial_db_setup(): void
    {
        $this->init_options();
        $defaults = Bootstrap::instance()->container()->get(LegacyDefaults::class)->fromSchema($this->schema);
        if (function_exists('do_action')) { do_action('vp_option_before_db_init', $defaults); }
        $this->options = $defaults;
        $result = $this->save_and_reinit();
        if (function_exists('do_action')) {
            do_action('vp_option_after_db_init', $defaults, $result['status'], $this->optionKey);
            do_action('vp_option_after_db_init-' . $this->optionKey, $defaults, $result['status']);
        }
    }

    /** Safe compatibility stubs: Pressundo does not register the old AJAX endpoints. */
    public function ajax_save(): void { $this->legacyAjaxUnavailable(); }
    public function ajax_restore(): void { $this->legacyAjaxUnavailable(); }
    public function ajax_import_option(): void { $this->legacyAjaxUnavailable(); }
    public function ajax_export_option(): void { $this->legacyAjaxUnavailable(); }
    public function vp_verify_nonce(): bool { return false; }

    private function legacyAjaxUnavailable(): void
    {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(['message' => 'Legacy Vafpress AJAX is disabled by Pressundo.'], 410);
        }
    }

    /** @return array<string,mixed> */
    public function get_options(): array { return $this->options; }
    /** @param array<string,mixed> $options */
    public function set_options($options): self
    {
        $this->options = is_array($options) ? $options : [];
        if ($this->optionsSet instanceof OptionSetAdapter) { $this->optionsSet->set_values($this->options); }
        return $this;
    }
    public function get_options_set(): mixed { $this->init_options_set(); return $this->optionsSet; }
    public function set_options_set($value): self { $this->optionsSet = $value; return $this; }
    public function get_option_key(): string { return $this->optionKey; }
    public function set_option_key($value): self { $this->optionKey = (string) $value; return $this; }
    public function get_page_slug(): string { return $this->pageSlug; }
    public function set_page_slug($value): self { $this->pageSlug = (string) $value; return $this; }
    public function get_template(): mixed { return $this->template; }
    public function set_template($value): self { $this->template = $value; return $this; }
    public function get_menu_page(): mixed { return $this->menuPage; }
    public function set_menu_page($value): self { $this->menuPage = $value; return $this; }
    public function get_layout(): string { return $this->layout; }
    public function set_layout($value): self { $this->layout = (string) $value; return $this; }
    public function get_menu_label(): string { return $this->menuLabel; }
    public function set_menu_label($value): self { $this->menuLabel = (string) $value; return $this; }
    public function get_page_title(): string { return $this->pageTitle; }
    public function set_page_title($value): self { $this->pageTitle = (string) $value; return $this; }
    public function get_minimum_role(): string { return $this->role; }
    public function set_minimum_role($value): self { $this->role = (string) $value; return $this; }
    public function get_hook_suffix(): string { return $this->hookSuffix; }
    public function set_hook_suffix($value): self { $this->hookSuffix = (string) $value; return $this; }
    public function use_auto_group_naming($value = null): mixed
    {
        if ($value === null) { return $this->useAutoGroupNaming; }
        $this->useAutoGroupNaming = (bool) $value;
        return null;
    }
    public function use_util_menu($value = null): mixed
    {
        if ($value === null) { return $this->useUtilMenu; }
        $this->useUtilMenu = (bool) $value;
        return null;
    }
    public function is_dev_mode($value = null): mixed
    {
        if ($value === null) { return $this->devMode; }
        $this->devMode = (bool) $value;
        return null;
    }
    public function create_get_option_helper(): null { return null; }
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

    private function initializeOptions(): void
    {
        if (! function_exists('get_option') || ! function_exists('add_option')) {
            $this->options = [];
            return;
        }

        $existing = get_option($this->optionKey, false);
        if ($existing === false) {
            /** @var LegacyDefaults $defaults */
            $defaults = Bootstrap::instance()->container()->get(LegacyDefaults::class);
            $initial = $defaults->fromSchema($this->schema);
            if (! $this->devMode) {
                add_option($this->optionKey, $initial, '', false);
            }
            $this->options = $initial;
            if (function_exists('do_action')) {
                do_action('vp_option_first_activation');
            }
            return;
        }

        $this->options = is_array($existing) ? $existing : [];
    }

    private function handleSave(): void
    {
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';
        if ($requestMethod !== 'POST') {
            return;
        }
        if (! isset($_POST['pressundo_vp_action'])) {
            return;
        }
        if (function_exists('current_user_can') && ! current_user_can($this->role)) {
            return;
        }
        if (function_exists('check_admin_referer')) {
            check_admin_referer('pressundo_vp_option_' . $this->optionKey, 'pressundo_vp_nonce');
        }
        $action = sanitize_key(wp_unslash($_POST['pressundo_vp_action']));
        if ($action !== 'save') {
            return;
        }

        $currentStored = function_exists('get_option') ? get_option($this->optionKey, []) : $this->options;
        $currentStored = is_array($currentStored) ? $currentStored : [];
        $revision = isset($_POST['pressundo_vp_revision'])
            ? sanitize_text_field(wp_unslash($_POST['pressundo_vp_revision']))
            : '';
        if ((string) $revision !== '' && ! SecurityPolicy::revisionsMatch($revision, $currentStored)) {
            if (function_exists('wp_safe_redirect') && function_exists('add_query_arg')) {
                wp_safe_redirect(add_query_arg('pressundo_vp_conflict', '1'));
                exit;
            }
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Schema-aware sanitization is performed by LegacyRuntimeSaveService below.
        $submitted = isset($_POST['pressundo_legacy_values']) ? wp_unslash($_POST['pressundo_legacy_values']) : [];
        if (! is_array($submitted)) {
            $submitted = [];
        }

        /** @var LegacyRuntimeSaveService $save */
        $save = Bootstrap::instance()->container()->get(LegacyRuntimeSaveService::class);
        $result = $save->sanitize($this->schema, $this->options, $submitted);
        $ok = ! empty($result['submission_gate_passed']) && is_array($result['payload'] ?? null);

        if ($ok && ! $this->devMode && function_exists('update_option')) {
            update_option($this->optionKey, $result['payload']);
            $this->options = (array) $result['payload'];
            if (function_exists('do_action')) {
                do_action('vp_option_save_and_reinit', $this->options, true, $this->optionKey);
                do_action('vp_option_save_and_reinit-' . $this->optionKey, $this->options, true);
            }
        }

        if (function_exists('wp_safe_redirect') && function_exists('add_query_arg')) {
            $url = add_query_arg($ok ? 'pressundo_vp_saved' : 'pressundo_vp_error', '1');
            wp_safe_redirect($url);
            exit;
        }
    }
}
