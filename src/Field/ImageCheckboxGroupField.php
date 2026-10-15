<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class ImageCheckboxGroupField extends MultiSelectField
{
    public function type(): string { return 'image_checkbox_group'; }

    public function render(array $field, mixed $value): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $selected = is_array($value) ? array_map('strval', $value) : [];
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $html = '<div class="pressundo-image-choices pressundo-image-choices--checkbox"><input type="hidden" name="' . esc_attr($name) . '[]" value="">';
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $image = is_array($option) ? (string) ($option['image'] ?? '') : '';
            $html .= '<label class="pressundo-image-choice"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr((string) $key) . '"' . checked(in_array((string) $key, $selected, true), true, false) . '>';
            $html .= '<span class="pressundo-image-choice__card">';
            if ($image !== '') { $html .= '<img src="' . esc_url($image) . '" alt="">'; }
            $html .= '<span>' . esc_html($label) . '</span></span></label>';
        }
        return $html . '</div>';
    }
}
