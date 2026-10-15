<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class ImageRadioField extends AbstractField
{
    public function type(): string { return 'image_radio'; }

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
        $html = '<div class="pressundo-image-choices pressundo-image-choices--radio">';
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $image = is_array($option) ? (string) ($option['image'] ?? '') : '';
            $html .= '<label class="pressundo-image-choice"><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr((string) $key) . '"' . checked((string) $value, (string) $key, false) . '>';
            $html .= '<span class="pressundo-image-choice__card">';
            if ($image !== '') { $html .= '<img src="' . esc_url($image) . '" alt="">'; }
            $html .= '<span>' . esc_html($label) . '</span></span></label>';
        }
        return $html . '</div>';
    }
}
