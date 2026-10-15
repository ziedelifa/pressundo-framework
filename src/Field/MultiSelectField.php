<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

class MultiSelectField extends AbstractField
{
    public function type(): string { return 'multi_select'; }
    public function sanitize(mixed $value, array $field): array
    {
        $values = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $result = [];
        foreach ($values as $item) {
            $item = sanitize_text_field((string) $item);
            if ($item !== '' && array_key_exists($item, $options) && ! in_array($item, $result, true)) { $result[] = $item; }
        }
        return $result;
    }
    public function render(array $field, mixed $value): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $id = sanitize_key((string) ($field['id'] ?? 'field'));
        $selectedValues = is_array($value) ? array_map('strval', $value) : [];
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $disabled = ! empty($field['disabled']) || ! empty($field['readonly']) ? ' disabled' : '';
        $required = ! empty($field['required']) ? ' required' : '';
        $searchable = ! empty($field['searchable']);
        $html = $searchable
            ? '<div class="pressundo-searchable-select is-multiple" data-pressundo-searchable-select><div class="pressundo-searchable-select__search"><span aria-hidden="true">⌕</span><input type="search" class="pressundo-searchable-select__input" placeholder="' . esc_attr((string) ($field['search_placeholder'] ?? __('Search choices…', 'pressundo-framework'))) . '" aria-label="' . esc_attr__('Search choices', 'pressundo-framework') . '"></div>'
            : '';
        $html .= '<select multiple id="' . esc_attr($id) . '" name="' . esc_attr($name) . '[]" class="regular-text pressundo-field__control"' . $disabled . $required . ($searchable ? ' data-pressundo-searchable-source' : '') . '>';
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= '<option value="' . esc_attr((string) $key) . '"' . selected(in_array((string) $key, $selectedValues, true), true, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        if ($searchable) {
            $html .= '<div class="pressundo-searchable-select__empty" hidden>' . esc_html__('No matching choices.', 'pressundo-framework') . '</div></div>';
        }
        return $html;
    }
}
