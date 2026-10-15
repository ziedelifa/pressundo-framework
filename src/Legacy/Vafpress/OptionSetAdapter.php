<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use Pressundo\Framework\Bootstrap;
use Pressundo\Framework\Renderer\SchemaFormRenderer;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exact vp_* hooks are part of the Vafpress compatibility contract.

/**
 * Compatibility object for the public parts of VP_Option_Control_Set that
 * legacy themes occasionally accessed through VP_Option::get_options_set().
 */
final class OptionSetAdapter
{
    public const SAVE_SUCCESS = 1;
    public const SAVE_NOCHANGES = 2;
    public const SAVE_FAILED = 3;

    /** @param array<string,mixed> $schema @param array<string,mixed> $values */
    public function __construct(
        private array $schema = [],
        private array $values = [],
        private string $optionKey = ''
    ) {}

    /** @return array<string,mixed> */
    public function get_values(): array { return $this->values; }

    /** @param array<string,mixed> $values */
    public function set_values(array $values): self { $this->values = $values; return $this; }

    /** @return array<string,mixed> */
    public function get_defaults(): array
    {
        return Bootstrap::instance()->container()->get(LegacyDefaults::class)->fromSchema($this->schema);
    }

    /** @param array<string,mixed> $values */
    public function populate_values($values, $force_update = false): void
    {
        if (! is_array($values)) { return; }
        if ($force_update) {
            $this->values = array_replace($this->get_defaults(), $values);
        } else {
            $this->values = array_replace($this->values, $values);
        }
    }

    /** @return list<string> */
    public function get_field_types(): array
    {
        $types = [];
        $walk = static function (array $fields) use (&$walk, &$types): void {
            foreach ($fields as $field) {
                if (! is_array($field)) { continue; }
                if (! empty($field['legacy_type'])) { $types[] = (string) $field['legacy_type']; }
                elseif (! empty($field['type'])) { $types[] = (string) $field['type']; }
                if (isset($field['fields']) && is_array($field['fields'])) { $walk($field['fields']); }
            }
        };
        foreach ((array) ($this->schema['sections'] ?? []) as $section) {
            if (is_array($section)) { $walk((array) ($section['fields'] ?? [])); }
        }
        return array_values(array_unique($types));
    }

    /** @return array{status:bool,code:int,message:string} */
    public function save($optionKey = null): array
    {
        $key = is_string($optionKey) && $optionKey !== '' ? $optionKey : $this->optionKey;
        if ($key === '' || ! function_exists('update_option')) {
            return ['status' => false, 'code' => self::SAVE_FAILED, 'message' => 'Saving failed'];
        }

        if (function_exists('do_action')) { do_action('vp_option_set_before_save', $this->values); }
        $before = function_exists('get_option') ? get_option($key, []) : [];
        $updated = update_option($key, $this->values);
        $after = function_exists('get_option') ? get_option($key, []) : $this->values;

        if ($updated) {
            $result = ['status' => true, 'code' => self::SAVE_SUCCESS, 'message' => 'Saving successful'];
        } elseif ($before === $after && $after === $this->values) {
            $result = ['status' => true, 'code' => self::SAVE_NOCHANGES, 'message' => 'No changes made'];
        } else {
            $result = ['status' => false, 'code' => self::SAVE_FAILED, 'message' => 'Saving failed'];
        }

        if (function_exists('do_action')) { do_action('vp_option_set_after_save', $after, $result['status'], $key); }
        return $result;
    }

    public function render(): string
    {
        $renderer = Bootstrap::instance()->container()->get(SchemaFormRenderer::class);
        return $renderer->render($this->schema, $this->values, false, 'pressundo_legacy_values', true);
    }

    public function setup($options = []): void
    {
        if (is_array($options)) { $this->values = $options; }
    }

    public function set_layout($layout): self { return $this; }
}
