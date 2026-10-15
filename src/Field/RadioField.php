<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class RadioField extends AbstractField
{
    public function type(): string { return 'radio'; }
    public function sanitize(mixed $value, array $field): string
    {
        $value = sanitize_text_field((string) $value);
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        return array_key_exists($value, $options) ? $value : '';
    }
    public function render(array $field, mixed $value): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $disabled = ! empty($field['disabled']) || ! empty($field['readonly']) ? ' disabled' : '';
        $required = ! empty($field['required']) ? ' required' : '';
        $html = '<div class="pressundo-radio-group">';
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= '<label><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr((string) $key) . '"' . checked((string) $value, (string) $key, false) . $disabled . $required . '> ' . esc_html($label) . '</label>';
        }
        return $html . '</div>';
    }
}
