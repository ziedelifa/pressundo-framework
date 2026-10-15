<?php

declare(strict_types=1);

namespace Pressundo\Framework\Admin;

final class FrameworkAdminPage
{
    public function __construct(private readonly AssetManager $assets) {}
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu(): void
    {
        add_management_page(
            __('Pressundo Framework', 'pressundo-framework'),
            __('Pressundo Framework', 'pressundo-framework'),
            'manage_options',
            'pressundo-framework',
            [$this, 'render']
        );
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'tools_page_pressundo-framework') {
            return;
        }

        $this->assets->enqueueStylesOnly();
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'pressundo-framework'));
        }

        echo '<div class="wrap pressundo-framework">';
        echo '<div class="pressundo-hero">';
        echo '<div><p class="pressundo-eyebrow">' . esc_html__('Pressundo Framework 0.13.0-dev', 'pressundo-framework') . '</p>';
        echo '<h1>' . esc_html__('Modern WordPress framework + progressive migration', 'pressundo-framework') . '</h1>';
        echo '<p>' . esc_html__('Build new projects with the modern API and modernize existing themes or plugins progressively without forcing client data migrations.', 'pressundo-framework') . '</p></div>';
        echo '<span class="pressundo-mode">' . esc_html__('Free 0.13', 'pressundo-framework') . '</span>';
        echo '</div>';

        echo '<div class="pressundo-grid">';
        $this->card(
            __('Modern API', 'pressundo-framework'),
            __('API v1: values, namespaces, complete Free field definitions, dynamic choices and conditional logic.', 'pressundo-framework'),
            __('Ready', 'pressundo-framework')
        );
        $this->card(
            __('Progressive Migration', 'pressundo-framework'),
            __('Use preserve, lazy or copy only where existing client data must remain compatible.', 'pressundo-framework'),
            __('Free', 'pressundo-framework')
        );
        $this->card(
            __('Legacy Compatibility', 'pressundo-framework'),
            __('Existing Vafpress public APIs can run on the Pressundo core while keeping the same option and post-meta keys.', 'pressundo-framework'),
            __('Automatic', 'pressundo-framework')
        );
        echo '</div>';

        echo '<div class="pressundo-panel pressundo-api-panel">';
        echo '<div class="pressundo-panel__head"><div><h2>' . esc_html__('Quick start', 'pressundo-framework') . '</h2>';
        echo '<p>' . esc_html__('No migration wizard is required. New projects use Pressundo directly; existing projects add migrate_from only where needed.', 'pressundo-framework') . '</p></div></div>';
        echo '<div class="pressundo-api-examples">';
        echo '<div><strong>' . esc_html__('New project', 'pressundo-framework') . '</strong><pre><code>' . esc_html("pu_set('theme_color', '#3858e9');\n\$color = pu_get('theme_color', '#000000');") . '</code></pre></div>';
        echo '<div><strong>' . esc_html__('Field definitions', 'pressundo-framework') . '</strong><pre><code>' . esc_html("\$fields = [\n    pu_text('site_title'),\n    pu_select('layout', ['wide' => 'Wide', 'boxed' => 'Boxed']),\n    pu_toggle('show_banner'),\n];") . '</code></pre></div>';
        echo '<div><strong>' . esc_html__('Existing project', 'pressundo-framework') . '</strong><pre><code>' . esc_html("\$theme = pu_options('theme', [\n    'storage_key'  => 'pressundo_theme',\n    'migrate_from' => 'old_theme_options',\n]);") . '</code></pre></div>';
        echo '</div>';
        echo '<p class="pressundo-plan__note">' . esc_html__('Default strategy: preserve. Existing client data stays where it is unless the developer explicitly chooses another migration strategy.', 'pressundo-framework') . '</p>';
        echo '</div>';

        echo '<div class="pressundo-panel">';
        echo '<div class="pressundo-panel__head"><div><h2>' . esc_html__('Migration strategies', 'pressundo-framework') . '</h2>';
        echo '<p>' . esc_html__('Choose the smallest strategy required by the code change.', 'pressundo-framework') . '</p></div></div>';
        echo '<div class="pressundo-grid">';
        $this->card(__('preserve', 'pressundo-framework'), __('Keep using existing storage when it already exists. No database copy.', 'pressundo-framework'), __('Default', 'pressundo-framework'));
        $this->card(__('lazy', 'pressundo-framework'), __('Copy an old value to modern storage only when that value is first needed.', 'pressundo-framework'), __('Optional', 'pressundo-framework'));
        $this->card(__('copy', 'pressundo-framework'), __('Use modern storage while temporarily synchronizing an existing legacy value for backward compatibility.', 'pressundo-framework'), __('Transition', 'pressundo-framework'));
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    private function card(string $title, string $body, string $badge): void
    {
        echo '<div class="pressundo-card"><span class="pressundo-badge">' . esc_html($badge) . '</span><h2>' . esc_html($title) . '</h2><p>' . esc_html($body) . '</p></div>';
    }
}
