<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class CheckboxGroupField extends MultiSelectField
{
    public function type(): string { return 'checkbox_group'; }
    public function render(array $field, mixed $value): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $selectedValues = is_array($value) ? array_map('strval', $value) : [];
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $disabled = ! empty($field['disabled']) || ! empty($field['readonly']) ? ' disabled' : '';
        $html = '<div class="pressundo-checkbox-group"><input type="hidden" name="' . esc_attr($name) . '[]" value="">';
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= '<label><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr((string) $key) . '"' . checked(in_array((string) $key, $selectedValues, true), true, false) . $disabled . '> ' . esc_html($label) . '</label>';
        }
        return $html . '</div>';
    }
}
