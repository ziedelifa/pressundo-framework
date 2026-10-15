<?php

/**
 * Global compatibility helpers matching the public Vafpress theme-author API.
 * Loaded only when Pressundo owns the Vafpress compatibility runtime.
 */

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Exact vp_* names are required for Vafpress compatibility.

if (! function_exists('vp_option')) {
    function vp_option($key, $default = null)
    {
        $keys = explode('.', (string) $key);
        $optionKey = array_shift($keys);
        $pool = \Pressundo\Framework\Legacy\Vafpress\OptionAdapter::get_pool();
        if (! isset($pool[$optionKey])) {
            return $default;
        }
        $value = $pool[$optionKey]->get_options();
        if (function_exists('get_option')) {
            $fresh = get_option($optionKey, $value);
            if (is_array($fresh)) {
                $value = $fresh;
                $pool[$optionKey]->set_options($fresh);
            }
        }
        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (! function_exists('vp_metabox')) {
    function vp_metabox($key, $default = null, $post_id = null)
    {
        $keys = explode('.', (string) $key);
        $id = array_shift($keys);
        $pool = \Pressundo\Framework\Legacy\Vafpress\MetaboxAdapter::get_pool();
        if (! isset($pool[$id])) {
            return $default;
        }
        $value = $pool[$id]->the_meta($post_id);
        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (! function_exists('vp_enqueue_scripts')) {
    function vp_enqueue_scripts(): void
    {
        foreach (\Pressundo\Framework\Legacy\Vafpress\OptionAdapter::get_pool() as $option) {
            $option->enqueue_scripts_and_styles();
        }
        foreach (\Pressundo\Framework\Legacy\Vafpress\MetaboxAdapter::get_pool() as $metabox) {
            if ($metabox->can_output()) {
                $metabox->enqueue_assets('');
            }
        }
    }
}

if (! function_exists('vp_get_categories')) {
    function vp_get_categories(): array
    {
        $result = [];
        foreach ((array) get_categories(['hide_empty' => 0]) as $term) {
            $id = isset($term->term_id) ? $term->term_id : ($term->cat_ID ?? 0);
            $result[] = ['value' => $id, 'label' => (string) ($term->name ?? '')];
        }
        return $result;
    }
}

if (! function_exists('vp_get_users')) {
    function vp_get_users(): array
    {
        $result = [];
        foreach ((array) get_users() as $user) {
            $result[] = ['value' => (int) ($user->ID ?? 0), 'label' => (string) ($user->display_name ?? '')];
        }
        return $result;
    }
}

if (! function_exists('vp_get_posts')) {
    function vp_get_posts(): array
    {
        $result = [];
        foreach ((array) get_posts(['posts_per_page' => -1]) as $post) {
            $result[] = ['value' => (int) ($post->ID ?? 0), 'label' => (string) ($post->post_title ?? '')];
        }
        return $result;
    }
}

if (! function_exists('vp_get_pages')) {
    function vp_get_pages(): array
    {
        $result = [];
        foreach ((array) get_pages() as $page) {
            $result[] = ['value' => (int) ($page->ID ?? 0), 'label' => (string) ($page->post_title ?? '')];
        }
        return $result;
    }
}

if (! function_exists('vp_get_tags')) {
    function vp_get_tags(): array
    {
        $result = [];
        foreach ((array) get_tags(['hide_empty' => 0]) as $term) {
            $result[] = ['value' => (int) ($term->term_id ?? 0), 'label' => (string) ($term->name ?? '')];
        }
        return $result;
    }
}

if (! function_exists('vp_get_roles')) {
    function vp_get_roles(): array
    {
        $roles = function_exists('get_editable_roles') ? get_editable_roles() : [];
        $result = [];
        foreach ((array) $roles as $key => $role) {
            $result[] = ['value' => (string) $key, 'label' => (string) ($role['name'] ?? $key)];
        }
        return $result;
    }
}

if (! function_exists('vp_get_gwf_family')) {
    function vp_get_gwf_family(): array
    {
        return array_map(
            static fn (string $font): array => ['value' => $font, 'label' => $font],
            array_keys(\Pressundo\Framework\Legacy\Vafpress\LegacyDataSources::googleFonts())
        );
    }
}

if (! function_exists('vp_get_gwf_weight')) {
    function vp_get_gwf_weight($face): array
    {
        $font = \Pressundo\Framework\Legacy\Vafpress\LegacyDataSources::googleFonts()[(string) $face] ?? null;
        $values = is_array($font) ? (array) ($font['weights'] ?? []) : [];
        return array_map(static fn ($v): array => ['value' => $v, 'label' => (string) $v], $values);
    }
}

if (! function_exists('vp_get_gwf_style')) {
    function vp_get_gwf_style($face): array
    {
        $font = \Pressundo\Framework\Legacy\Vafpress\LegacyDataSources::googleFonts()[(string) $face] ?? null;
        $values = is_array($font) ? (array) ($font['styles'] ?? []) : [];
        return array_map(static fn ($v): array => ['value' => $v, 'label' => (string) $v], $values);
    }
}

if (! function_exists('vp_get_gwf_subset')) {
    function vp_get_gwf_subset($face): array
    {
        $font = \Pressundo\Framework\Legacy\Vafpress\LegacyDataSources::googleFonts()[(string) $face] ?? null;
        $values = is_array($font) ? (array) ($font['subsets'] ?? []) : [];
        return array_map(static fn ($v): array => ['value' => $v, 'label' => (string) $v], $values);
    }
}

if (! function_exists('vp_get_social_medias')) {
    function vp_get_social_medias(): array
    {
        $names = [
            'blogger' => 'Blogger', 'delicious' => 'Delicious', 'deviantart' => 'DeviantArt',
            'digg' => 'Digg', 'dribbble' => 'Dribbble', 'email' => 'Email', 'facebook' => 'Facebook',
            'flickr' => 'Flickr', 'forrst' => 'Forrst', 'foursquare' => 'Foursquare', 'github' => 'Github',
            'googleplus' => 'Google+', 'instagram' => 'Instagram', 'lastfm' => 'Last.FM', 'linkedin' => 'LinkedIn',
            'myspace' => 'MySpace', 'pinterest' => 'Pinterest', 'reddit' => 'Reddit', 'rss' => 'RSS',
            'soundcloud' => 'SoundCloud', 'stumbleupon' => 'StumbleUpon', 'tumblr' => 'Tumblr',
            'twitter' => 'Twitter', 'vimeo' => 'Vimeo', 'wordpress' => 'WordPress', 'yahoo' => 'Yahoo!',
            'youtube' => 'Youtube',
        ];
        $result = [];
        foreach ($names as $value => $label) { $result[] = ['value' => $value, 'label' => $label]; }
        return $result;
    }
}

if (! function_exists('vp_get_fontawesome_icons')) {
    function vp_get_fontawesome_icons(): array
    {
        return array_map(
            static fn (string $icon): array => ['value' => $icon, 'label' => $icon],
            \Pressundo\Framework\Legacy\Vafpress\LegacyDataSources::fontAwesomeIcons()
        );
    }
}

if (! function_exists('vp_dep_boolean')) {
    function vp_dep_boolean($value): bool
    {
        foreach (func_get_args() as $arg) {
            if (empty($arg)) { return false; }
        }
        return true;
    }
}

// Vafpress itself whitelisted these dependency callbacks during bootstrap.
if (class_exists('VP_Security', false)) {
    foreach (['vp_get_gwf_weight', 'vp_get_gwf_style', 'vp_get_gwf_subset', 'vp_dep_boolean'] as $pressundo_vp_callback) {
        VP_Security::instance()->whitelist_function($pressundo_vp_callback);
    }
}
