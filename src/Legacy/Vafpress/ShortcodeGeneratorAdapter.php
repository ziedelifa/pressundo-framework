<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use Pressundo\Framework\Bootstrap;

/**
 * Compatibility facade for VP_ShortcodeGenerator.
 *
 * Vafpress's editor button depended on obsolete TinyMCE/Quicktags scripts and
 * its generic client-side runtime. Pressundo preserves construction, pool
 * access, field discovery and shortcode-tag discovery so themes do not fatal,
 * while intentionally not loading the old UI runtime.
 */
final class ShortcodeGeneratorAdapter
{
    /** @var array<string,self> */
    public static array $pool = [];

    public string $name = '';
    public mixed $template = [];
    public string $modal_title = '';
    public string $button_title = '';
    public string $main_image = '';
    public string $sprite_image = '';
    /** @var list<string> */
    public array $types = ['post', 'page'];
    /** @var list<string> */
    public array $include_pages = [];
    /** @var list<string> Historical property spelling used by Vafpress internally. */
    public array $included_pages = [];

    public function __construct($arr)
    {
        if (! is_array($arr)) {
            return;
        }
        foreach ($arr as $name => $value) {
            if (property_exists($this, (string) $name)) {
                $this->{(string) $name} = $value;
            }
        }
        if (is_string($this->template) && is_readable($this->template)) {
            try {
                /** @var LegacyTemplateLoader $loader */
                $loader = Bootstrap::instance()->container()->get(LegacyTemplateLoader::class);
                $this->template = $loader->load($this->template);
            } catch (\Throwable) {
                $this->template = [];
            }
        }
        if ($this->include_pages !== [] && $this->included_pages === []) {
            $this->included_pages = $this->include_pages;
        }
        $this->normalize();
        if ($this->name !== '') {
            self::$pool[$this->name] = $this;
        }
    }

    /** @return array<string,self> */
    public static function get_pool(): array { return self::$pool; }

    public function init_mce_plugin(): void
    {
        // The obsolete Vafpress TinyMCE plugin is intentionally not restored.
        // Keep the method callable for source compatibility.
    }

    public function normalize(): void
    {
        if (! is_array($this->template)) { $this->template = []; return; }
        foreach ($this->template as &$menu) {
            if (! is_array($menu) || ! isset($menu['elements']) || ! is_array($menu['elements'])) { continue; }
            foreach ($menu['elements'] as &$element) {
                if (! is_array($element) || ! isset($element['attributes']) || ! is_array($element['attributes'])) { continue; }
                foreach ($element['attributes'] as &$field) {
                    if (is_array($field) && ($field['type'] ?? '') === 'codeeditor') { $field['type'] = 'textarea'; }
                }
            }
        }
        unset($menu, $element, $field);
    }

    /** @return list<string> */
    public function get_field_types(): array
    {
        $types = [];
        foreach ((array) $this->template as $menu) {
            foreach ((array) (is_array($menu) ? ($menu['elements'] ?? []) : []) as $element) {
                foreach ((array) (is_array($element) ? ($element['attributes'] ?? []) : []) as $field) {
                    if (is_array($field) && ! empty($field['type'])) { $types[] = (string) $field['type']; }
                }
            }
        }
        return array_values(array_unique($types));
    }

    public static function pool_supports_editor(): bool
    {
        foreach (self::$pool as $generator) { if ($generator->supports_editor()) { return true; } }
        return false;
    }

    public function supports_editor(): bool
    {
        $type = MetaboxAdapter::_get_current_post_type();
        return $type === '' || ! function_exists('post_type_supports') || post_type_supports($type, 'editor');
    }

    public static function pool_can_output(): bool
    {
        foreach (self::$pool as $generator) { if ($generator->can_output()) { return true; } }
        return false;
    }

    public function can_output(): bool
    {
        $type = MetaboxAdapter::_get_current_post_type();
        if ($type !== '') {
            return in_array('*', $this->types, true) || in_array($type, $this->types, true);
        }
        if (! function_exists('get_current_screen')) { return function_exists('is_admin') ? is_admin() : false; }
        $screen = get_current_screen();
        $id = is_object($screen) && isset($screen->id) ? (string) $screen->id : '';
        return $id !== '' && in_array($id, $this->included_pages, true);
    }

    public function print_modal(): void {}
    public function print_form($attributes): void {}

    /** @return list<array{name:string,modal_title:string,button_title:string,main_image:string,sprite_image:string}> */
    public static function build_localize(): array
    {
        $out = [];
        foreach (self::$pool as $generator) {
            $out[] = [
                'name' => $generator->name,
                'modal_title' => $generator->modal_title,
                'button_title' => $generator->button_title,
                'main_image' => $generator->main_image,
                'sprite_image' => $generator->sprite_image,
            ];
        }
        return $out;
    }

    public static function init_buttons(): void {}
    public static function print_styles($buttons = null): void {}
    public static function register_buttons($buttons): array { return is_array($buttons) ? $buttons : []; }
    public static function add_buttons($plugin_array): array { return is_array($plugin_array) ? $plugin_array : []; }
    public static function fullscreen_buttons($buttons): array { return is_array($buttons) ? $buttons : []; }

    /** @return list<string> */
    public function get_shortcode_tags(): array
    {
        $tags = [];
        foreach ((array) $this->template as $menu) {
            foreach ((array) (is_array($menu) ? ($menu['elements'] ?? []) : []) as $element) {
                $code = is_array($element) ? (string) ($element['code'] ?? '') : '';
                if (preg_match('/\[([A-Za-z0-9_:-]+)/', $code, $match) === 1) { $tags[] = $match[1]; }
            }
        }
        return array_values(array_unique($tags));
    }
}
