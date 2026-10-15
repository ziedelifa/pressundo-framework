<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class SortableField extends MultiSelectField
{
    public function type(): string { return 'sortable_select'; }
    public function render(array $field, mixed $value): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $selected = is_array($value) ? array_values(array_map('strval', $value)) : [];
        $max = isset($field['max_selection']) && is_numeric($field['max_selection']) ? max(0, (int) $field['max_selection']) : 0;
        $html = '<div class="pressundo-sortable-select" data-pressundo-sortable="1" data-field-name="' . esc_attr($name) . '" data-max-selection="' . esc_attr((string) $max) . '"><ol>';
        foreach ($selected as $key) {
            if (! array_key_exists($key, $options)) { continue; }
            $option = $options[$key];
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= '<li data-value="' . esc_attr($key) . '" data-label="' . esc_attr($label) . '" draggable="true"><button type="button" class="pressundo-icon-button pressundo-drag-handle pressundo-sortable-drag" aria-label="' . esc_attr__('Drag to reorder item', 'pressundo-framework') . '"><span aria-hidden="true">⋮⋮</span></button><span class="pressundo-sortable-label">' . esc_html($label) . '</span><input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($key) . '"><details class="pressundo-row-menu"><summary aria-label="' . esc_attr__('Item actions', 'pressundo-framework') . '">•••</summary><div class="pressundo-row-menu__panel"><button type="button" class="pressundo-row-menu__action pressundo-sortable-up">' . esc_html__('Move up', 'pressundo-framework') . '</button><button type="button" class="pressundo-row-menu__action pressundo-sortable-down">' . esc_html__('Move down', 'pressundo-framework') . '</button><button type="button" class="pressundo-row-menu__action is-danger pressundo-sortable-remove">' . esc_html__('Delete', 'pressundo-framework') . '</button></div></details></li>';
        }
        $html .= '</ol><div class="pressundo-sortable-select__available"><select class="pressundo-sortable-available"><option value="">' . esc_html__('Choose an item…', 'pressundo-framework') . '</option>';
        foreach ($options as $key => $option) {
            if (in_array((string) $key, $selected, true)) { continue; }
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= '<option value="' . esc_attr((string) $key) . '">' . esc_html($label) . '</option>';
        }
        return $html . '</select><button type="button" class="button pressundo-sortable-add">' . esc_html__('Add', 'pressundo-framework') . '</button></div></div>';
    }
}
