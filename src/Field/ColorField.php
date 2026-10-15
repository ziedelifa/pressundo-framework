<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class ColorField extends AbstractField
{
    public function type(): string { return 'color'; }

    public function sanitize(mixed $value, array $field): string
    {
        $color = sanitize_hex_color(trim((string) $value));
        return $color ? self::normalizeHex($color) : '';
    }

    public function render(array $field, mixed $value): string
    {
        $hex = sanitize_hex_color(trim((string) $value));
        $hex = $hex ? self::normalizeHex($hex) : '';
        $picker = $hex !== '' ? $hex : '#000000';
        $id = sanitize_key((string) ($field['id'] ?? $field['name'] ?? 'field'));
        $name = (string) ($field['name'] ?? $id);
        $disabled = (! empty($field['disabled']) || ! empty($field['readonly'])) ? ' disabled' : '';

        return sprintf(
            '<div class="pressundo-color-field" data-pressundo-color-field>'
            . '<input type="hidden" id="%1$s" name="%2$s" value="%3$s" data-pressundo-color-value>'
            . '<details class="pressundo-color-popover">'
            . '<summary class="pressundo-color-trigger" aria-label="%4$s"><span class="pressundo-color-swatch" style="--pressundo-color:%5$s"></span><span class="pressundo-color-current">%3$s</span></summary>'
            . '<div class="pressundo-color-popover__panel">'
            . '<input type="color" class="pressundo-color-picker" value="%5$s" data-pressundo-color-picker aria-label="%4$s"%6$s>'
            . '<label class="pressundo-color-popover__hex"><span>HEX</span><input type="text" value="%3$s" data-pressundo-color-hex maxlength="7" pattern="#[0-9A-Fa-f]{6}" spellcheck="false" autocomplete="off" placeholder="#000000"%6$s></label>'
            . '</div></details></div>',
            esc_attr($id),
            esc_attr($name),
            esc_attr($hex),
            esc_attr__('Choose color', 'pressundo-framework'),
            esc_attr($picker),
            $disabled
        );
    }

    private static function normalizeHex(string $hex): string
    {
        $hex = strtolower($hex);
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $hex, $matches)) {
            $hex = '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
        }
        return strtoupper($hex);
    }
}
