<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class SelectField extends AbstractField
{
    public function type(): string { return 'select'; }

    public function sanitize(mixed $value, array $field): string
    {
        $value = sanitize_text_field((string) $value);
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        return array_key_exists($value, $options) ? $value : '';
    }

    public function render(array $field, mixed $value): string
    {
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $searchable = ! empty($field['searchable']);
        $html = $searchable
            ? '<div class="pressundo-searchable-select" data-pressundo-searchable-select><div class="pressundo-searchable-select__search"><span aria-hidden="true">⌕</span><input type="search" class="pressundo-searchable-select__input" placeholder="' . esc_attr((string) ($field['search_placeholder'] ?? __('Search choices…', 'pressundo-framework'))) . '" aria-label="' . esc_attr__('Search choices', 'pressundo-framework') . '"></div>'
            : '';

        $html .= '<select ' . $this->commonAttributes($field) . ($searchable ? ' data-pressundo-searchable-source' : '') . '>';
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= sprintf('<option value="%s"%s>%s</option>', esc_attr((string) $key), selected((string) $value, (string) $key, false), esc_html($label));
        }
        $html .= '</select>';

        if ($searchable) {
            $html .= '<div class="pressundo-searchable-select__empty" hidden>' . esc_html__('No matching choices.', 'pressundo-framework') . '</div></div>';
        }

        return $html;
    }
}
