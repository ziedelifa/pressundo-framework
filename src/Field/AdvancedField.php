<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

use Pressundo\Framework\Contracts\FieldTypeInterface;

/**
 * Shared implementation for Pressundo's composite/advanced Free fields.
 * Public field types stay independent while sharing one consistent storage contract.
 */
final class AdvancedField implements FieldTypeInterface
{
    public function __construct(private readonly string $fieldType) {}

    public function type(): string
    {
        return $this->fieldType;
    }

    /** @param array<string,mixed> $field */
    public function sanitize(mixed $value, array $field): mixed
    {
        return match ($this->fieldType) {
            'font_family', 'font_weight', 'unit', 'button_group', 'password', 'hidden' => sanitize_text_field((string) $value),
            'datetime' => $this->sanitizeDateTime($value),
            'post_select', 'term_select', 'user_select', 'menu_select' => ! empty($field['multiple'])
                ? $this->sanitizeIds($value)
                : absint($value),
            'sidebar_select', 'template_select' => sanitize_text_field((string) $value),
            'typography' => $this->sanitizeTypography($value),
            'spacing' => $this->sanitizeBoxValues($value, ['top', 'right', 'bottom', 'left']),
            'dimensions' => $this->sanitizeBoxValues($value, ['width', 'height', 'min_width', 'max_width', 'min_height', 'max_height']),
            'border_radius' => $this->sanitizeBoxValues($value, ['top_left', 'top_right', 'bottom_right', 'bottom_left']),
            'border' => $this->sanitizeBorder($value),
            'gradient' => $this->sanitizeGradient($value),
            'background' => $this->sanitizeBackground($value),
            'box_shadow' => $this->sanitizeShadow($value),
            'responsive' => $this->sanitizeResponsive($value, $field),
            'key_value' => $this->sanitizeKeyValue($value),
            'json' => $this->sanitizeCode((string) $value),
            'css', 'javascript' => $this->sanitizeCode((string) $value),
            'heading', 'divider' => null,
            default => is_scalar($value) ? sanitize_text_field((string) $value) : $value,
        };
    }

    /** @param array<string,mixed> $field */
    public function render(array $field, mixed $value): string
    {
        return match ($this->fieldType) {
            'font_family' => $this->renderFontFamily($field, $value),
            'font_weight' => $this->renderSelect($field, $value, $this->fontWeights()),
            'unit' => $this->renderSelect($field, $value, $this->units($field)),
            'button_group' => $this->renderButtonGroup($field, $value),
            'datetime' => $this->renderSimpleInput($field, $value, 'datetime-local'),
            'hidden' => $this->renderSimpleInput($field, $value, 'hidden'),
            'password' => $this->renderSimpleInput($field, $value, 'password', 'new-password'),
            'post_select', 'term_select', 'user_select', 'menu_select', 'sidebar_select', 'template_select' => $this->renderSelect($field, $value, (array) ($field['options'] ?? []), ! empty($field['multiple'])),
            'typography' => $this->renderTypography($field, $value),
            'spacing' => $this->renderBoxControl($field, $value, ['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'], 'spacing'),
            'dimensions' => $this->renderBoxControl($field, $value, ['width' => 'Width', 'height' => 'Height', 'min_width' => 'Min width', 'max_width' => 'Max width', 'min_height' => 'Min height', 'max_height' => 'Max height'], 'dimensions'),
            'border_radius' => $this->renderBoxControl($field, $value, ['top_left' => 'Top left', 'top_right' => 'Top right', 'bottom_right' => 'Bottom right', 'bottom_left' => 'Bottom left'], 'radius'),
            'border' => $this->renderBorder($field, $value),
            'gradient' => $this->renderGradient($field, $value),
            'background' => $this->renderBackground($field, $value),
            'box_shadow' => $this->renderShadow($field, $value),
            'responsive' => $this->renderResponsive($field, $value),
            'key_value' => $this->renderKeyValue($field, $value),
            'json' => $this->renderCodeArea($field, $value, 'json'),
            'css' => $this->renderCodeArea($field, $value, 'css'),
            'javascript' => $this->renderCodeArea($field, $value, 'javascript'),
            'heading' => '<div class="pressundo-presentation-heading"><strong>' . esc_html((string) ($field['text'] ?? $field['label'] ?? 'Heading')) . '</strong>' . (! empty($field['description']) ? '<span>' . esc_html((string) $field['description']) . '</span>' : '') . '</div>',
            'divider' => '<hr class="pressundo-divider" aria-hidden="true">',
            default => '',
        };
    }

    /** @param array<string,mixed> $field */
    private function renderSimpleInput(array $field, mixed $value, string $type, string $autocomplete = ''): string
    {
        $attrs = $this->attrs($field);
        if ($autocomplete !== '') { $attrs .= ' autocomplete="' . esc_attr($autocomplete) . '"'; }
        return '<input type="' . esc_attr($type) . '" ' . $attrs . ' value="' . esc_attr(is_scalar($value) ? (string) $value : '') . '">';
    }

    /** @param array<string,mixed> $field @param array<string|int,mixed> $options */
    private function renderSelect(array $field, mixed $value, array $options, bool $multiple = false): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $id = sanitize_key((string) ($field['id'] ?? 'field'));
        $selected = is_array($value) ? array_map('strval', $value) : [(string) $value];
        $searchable = ! empty($field['searchable']);
        $html = '<div class="pressundo-advanced-select' . ($searchable ? ' pressundo-searchable-select' : '') . '"' . ($searchable ? ' data-pressundo-searchable-select' : '') . '>';
        if ($searchable) {
            $html .= '<div class="pressundo-searchable-select__search"><span aria-hidden="true">⌕</span><input type="search" class="pressundo-searchable-select__input" placeholder="' . esc_attr__('Search choices…', 'pressundo-framework') . '" aria-label="' . esc_attr__('Search choices', 'pressundo-framework') . '"></div>';
        }
        if ($multiple) { $html .= '<input type="hidden" name="' . esc_attr($name) . '[]" value="">'; }
        $html .= '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . ($multiple ? '[]' : '') . '" class="pressundo-field__control"' . ($multiple ? ' multiple' : '') . ($searchable ? ' data-pressundo-searchable-source' : '') . (! empty($field['required']) ? ' required aria-required="true"' : '') . '>';
        if (! $multiple && empty($field['required'])) { $html .= '<option value="">' . esc_html__('Choose…', 'pressundo-framework') . '</option>'; }
        foreach ($options as $key => $option) {
            $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
            $html .= '<option value="' . esc_attr((string) $key) . '"' . selected(in_array((string) $key, $selected, true), true, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        if ($searchable) { $html .= '<div class="pressundo-searchable-select__empty" hidden>' . esc_html__('No matching choices.', 'pressundo-framework') . '</div>'; }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderFontFamily(array $field, mixed $value): string
    {
        $fonts = (array) ($field['options'] ?? []);
        if ($fonts === []) {
            $fonts = [
                'system-ui' => 'System UI', 'Inter' => 'Inter', 'Arial' => 'Arial', 'Helvetica' => 'Helvetica',
                'Georgia' => 'Georgia', 'Times New Roman' => 'Times New Roman', 'Verdana' => 'Verdana',
                'Tahoma' => 'Tahoma', 'Trebuchet MS' => 'Trebuchet MS', 'Courier New' => 'Courier New',
                'Roboto' => 'Roboto', 'Open Sans' => 'Open Sans', 'Montserrat' => 'Montserrat', 'Poppins' => 'Poppins',
            ];
        }
        $field['searchable'] = $field['searchable'] ?? true;
        return $this->renderSelect($field, $value, $fonts);
    }

    /** @param array<string,mixed> $field */
    private function renderButtonGroup(array $field, mixed $value): string
    {
        $options = (array) ($field['options'] ?? []);
        $name = (string) ($field['name'] ?? 'field');
        $html = '<div class="pressundo-button-group" role="radiogroup">';
        foreach ($options as $key => $label) {
            $label = is_array($label) ? (string) ($label['label'] ?? $key) : (string) $label;
            $html .= '<label><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr((string) $key) . '"' . checked((string) $value, (string) $key, false) . '><span>' . esc_html($label) . '</span></label>';
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderTypography(array $field, mixed $value): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? 'typography');
        $familyField = ['name' => $name . '[font_family]', 'id' => sanitize_key($name . '_font_family'), 'searchable' => true, 'options' => (array) ($field['fonts'] ?? [])];
        $weightField = ['name' => $name . '[font_weight]', 'id' => sanitize_key($name . '_font_weight')];
        $html = '<div class="pressundo-composite pressundo-typography">';
        $html .= '<div class="pressundo-composite__wide"><span class="pressundo-sub-label">' . esc_html__('Font family', 'pressundo-framework') . '</span>' . $this->renderFontFamily($familyField, $v['font_family'] ?? 'system-ui') . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Weight', 'pressundo-framework') . '</span>' . $this->renderSelect($weightField, $v['font_weight'] ?? '400', $this->fontWeights()) . '</div>';
        $html .= $this->numberUnitPair($name, 'font_size', 'Font size', $v, $field['units'] ?? ['px','rem','em','vw'], '16', 'px');
        $html .= $this->simpleSubInput($name, 'line_height', 'Line height', $v['line_height'] ?? '1.5', 'number', '0.1');
        $html .= $this->numberUnitPair($name, 'letter_spacing', 'Letter spacing', $v, ['px','em','rem'], '0', 'px');
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Transform', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'text_transform', $v['text_transform'] ?? 'none', ['none'=>'None','uppercase'=>'Uppercase','lowercase'=>'Lowercase','capitalize'=>'Capitalize']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Decoration', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'text_decoration', $v['text_decoration'] ?? 'none', ['none'=>'None','underline'=>'Underline','line-through'=>'Line through']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Color', 'pressundo-framework') . '</span><input type="color" name="' . esc_attr($name . '[color]') . '" value="' . esc_attr($this->color($v['color'] ?? '#111827')) . '"></div>';
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field @param array<string,string> $parts */
    private function renderBoxControl(array $field, mixed $value, array $parts, string $class): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? $class);
        $units = $this->units($field);
        $unit = (string) ($v['unit'] ?? $field['default_unit'] ?? 'px');
        $html = '<div class="pressundo-composite pressundo-box-control pressundo-' . esc_attr($class) . '"><div class="pressundo-box-control__grid">';
        foreach ($parts as $key => $label) {
            $html .= '<label><span class="pressundo-sub-label">' . esc_html($label) . '</span><input type="text" inputmode="decimal" name="' . esc_attr($name . '[' . $key . ']') . '" value="' . esc_attr((string) ($v[$key] ?? '')) . '"></label>';
        }
        $html .= '</div><label class="pressundo-unit-select"><span class="pressundo-sub-label">' . esc_html__('Unit', 'pressundo-framework') . '</span><select name="' . esc_attr($name . '[unit]') . '">';
        foreach ($units as $u => $label) { $html .= '<option value="' . esc_attr((string) $u) . '"' . selected($unit, (string) $u, false) . '>' . esc_html((string) $label) . '</option>'; }
        $html .= '</select></label><button type="button" class="button pressundo-link-values" aria-pressed="false" title="' . esc_attr__('Link values', 'pressundo-framework') . '">🔗 ' . esc_html__('Link', 'pressundo-framework') . '</button></div>';
        return $html;
    }

    /** @param array<string,mixed> $field */
    private function renderBorder(array $field, mixed $value): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? 'border');
        $html = '<div class="pressundo-composite pressundo-border-control">';
        $html .= $this->simpleSubInput($name, 'width', 'Width', $v['width'] ?? '1', 'number', '1');
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Style', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'style', $v['style'] ?? 'solid', ['none'=>'None','solid'=>'Solid','dashed'=>'Dashed','dotted'=>'Dotted','double'=>'Double']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Color', 'pressundo-framework') . '</span><input type="color" name="' . esc_attr($name . '[color]') . '" value="' . esc_attr($this->color($v['color'] ?? '#d0d5dd')) . '"></div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Unit', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'unit', $v['unit'] ?? 'px', $this->units($field)) . '</div>';
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderGradient(array $field, mixed $value): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? 'gradient');
        $html = '<div class="pressundo-composite pressundo-gradient-control">';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Type', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'type', $v['type'] ?? 'linear', ['linear'=>'Linear','radial'=>'Radial']) . '</div>';
        $html .= $this->simpleSubInput($name, 'angle', 'Angle', $v['angle'] ?? '135', 'number', '1');
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Start', 'pressundo-framework') . '</span><input type="color" name="' . esc_attr($name . '[start_color]') . '" value="' . esc_attr($this->color($v['start_color'] ?? '#2563eb')) . '"></div>';
        $html .= $this->simpleSubInput($name, 'start_position', 'Start %', $v['start_position'] ?? '0', 'number', '1');
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('End', 'pressundo-framework') . '</span><input type="color" name="' . esc_attr($name . '[end_color]') . '" value="' . esc_attr($this->color($v['end_color'] ?? '#7c3aed')) . '"></div>';
        $html .= $this->simpleSubInput($name, 'end_position', 'End %', $v['end_position'] ?? '100', 'number', '1');
        $preview = $this->gradientCss($v);
        $html .= '<div class="pressundo-gradient-preview" style="background:' . esc_attr($preview) . '"></div></div>';
        return $html;
    }

    /** @param array<string,mixed> $field */
    private function renderBackground(array $field, mixed $value): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? 'background');
        $html = '<div class="pressundo-composite pressundo-background-control">';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Type', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'type', $v['type'] ?? 'color', ['color'=>'Color','image'=>'Image','gradient'=>'Gradient']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Color', 'pressundo-framework') . '</span><input type="color" name="' . esc_attr($name . '[color]') . '" value="' . esc_attr($this->color($v['color'] ?? '#ffffff')) . '"></div>';
        $html .= '<div class="pressundo-media-field pressundo-background-image" data-pressundo-media-preview data-return="id"><span class="pressundo-sub-label">' . esc_html__('Image', 'pressundo-framework') . '</span><div class="pressundo-media-field__controls"><input type="text" class="pressundo-field__control" name="' . esc_attr($name . '[image]') . '" value="' . esc_attr((string) ($v['image'] ?? '')) . '"><button type="button" class="button pressundo-media-select">' . esc_html__('Choose image', 'pressundo-framework') . '</button><button type="button" class="button-link pressundo-media-clear">' . esc_html__('Clear', 'pressundo-framework') . '</button></div><div class="pressundo-media-preview"></div></div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Position', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'position', $v['position'] ?? 'center center', ['left top'=>'Left top','center top'=>'Center top','right top'=>'Right top','left center'=>'Left center','center center'=>'Center center','right center'=>'Right center','left bottom'=>'Left bottom','center bottom'=>'Center bottom','right bottom'=>'Right bottom']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Size', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'size', $v['size'] ?? 'cover', ['auto'=>'Auto','cover'=>'Cover','contain'=>'Contain']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Repeat', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'repeat', $v['repeat'] ?? 'no-repeat', ['no-repeat'=>'No repeat','repeat'=>'Repeat','repeat-x'=>'Repeat X','repeat-y'=>'Repeat Y']) . '</div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Attachment', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'attachment', $v['attachment'] ?? 'scroll', ['scroll'=>'Scroll','fixed'=>'Fixed']) . '</div>';
        $gradientField = $field; $gradientField['name'] = $name . '[gradient]';
        $html .= '<div class="pressundo-composite__wide"><span class="pressundo-sub-label">' . esc_html__('Gradient', 'pressundo-framework') . '</span>' . $this->renderGradient($gradientField, $v['gradient'] ?? []) . '</div>';
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderShadow(array $field, mixed $value): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? 'box_shadow');
        $html = '<div class="pressundo-composite pressundo-shadow-control">';
        foreach (['x'=>'X','y'=>'Y','blur'=>'Blur','spread'=>'Spread'] as $key => $label) { $html .= $this->simpleSubInput($name, $key, $label, $v[$key] ?? ($key === 'blur' ? '12' : '0'), 'number', '1'); }
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Color', 'pressundo-framework') . '</span><input type="color" name="' . esc_attr($name . '[color]') . '" value="' . esc_attr($this->color($v['color'] ?? '#000000')) . '"></div>';
        $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Unit', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'unit', $v['unit'] ?? 'px', $this->units($field)) . '</div>';
        $html .= '<label class="pressundo-inline-check"><input type="checkbox" name="' . esc_attr($name . '[inset]') . '" value="1"' . checked(! empty($v['inset']), true, false) . '> ' . esc_html__('Inset', 'pressundo-framework') . '</label>';
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderResponsive(array $field, mixed $value): string
    {
        $v = is_array($value) ? $value : [];
        $name = (string) ($field['name'] ?? 'responsive');
        $requestedType = (string) ($field['value_type'] ?? 'number');
        $inputType = in_array($requestedType, ['text','number'], true) ? $requestedType : 'number';
        $html = '<div class="pressundo-composite pressundo-responsive-control">';
        foreach (['desktop'=>'Desktop','tablet'=>'Tablet','mobile'=>'Mobile'] as $key => $label) { $html .= $this->simpleSubInput($name, $key, $label, $v[$key] ?? '', $inputType, (string) ($field['step'] ?? '1')); }
        if ($inputType === 'number') { $html .= '<div><span class="pressundo-sub-label">' . esc_html__('Unit', 'pressundo-framework') . '</span>' . $this->subSelect($name, 'unit', $v['unit'] ?? ($field['default_unit'] ?? 'px'), $this->units($field)) . '</div>'; }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderKeyValue(array $field, mixed $value): string
    {
        $rows = is_array($value) ? array_values($value) : [];
        if ($rows === []) { $rows = [['key'=>'','value'=>'']]; }
        $name = (string) ($field['name'] ?? 'key_value');
        $html = '<div class="pressundo-key-value" data-pressundo-key-value="1"><div class="pressundo-key-value__rows">';
        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $html .= '<div class="pressundo-key-value__row"><span class="pressundo-drag-handle" aria-hidden="true">⋮⋮</span><input type="text" name="' . esc_attr($name . '[' . $index . '][key]') . '" placeholder="' . esc_attr__('Key', 'pressundo-framework') . '" value="' . esc_attr((string) ($row['key'] ?? '')) . '"><input type="text" name="' . esc_attr($name . '[' . $index . '][value]') . '" placeholder="' . esc_attr__('Value', 'pressundo-framework') . '" value="' . esc_attr((string) ($row['value'] ?? '')) . '"><button type="button" class="button-link-delete pressundo-key-value-remove">' . esc_html__('Remove', 'pressundo-framework') . '</button></div>';
        }
        $html .= '</div><button type="button" class="button pressundo-key-value-add">' . esc_html__('Add pair', 'pressundo-framework') . '</button><template><div class="pressundo-key-value__row"><span class="pressundo-drag-handle" aria-hidden="true">⋮⋮</span><input type="text" data-name-template="' . esc_attr($name . '[__INDEX__][key]') . '" placeholder="' . esc_attr__('Key', 'pressundo-framework') . '"><input type="text" data-name-template="' . esc_attr($name . '[__INDEX__][value]') . '" placeholder="' . esc_attr__('Value', 'pressundo-framework') . '"><button type="button" class="button-link-delete pressundo-key-value-remove">' . esc_html__('Remove', 'pressundo-framework') . '</button></div></template></div>';
        return $html;
    }

    /** @param array<string,mixed> $field */
    private function renderCodeArea(array $field, mixed $value, string $language): string
    {
        $rows = isset($field['rows']) ? max(4, (int) $field['rows']) : 10;
        return '<div class="pressundo-special-code pressundo-special-code--' . esc_attr($language) . '"><span class="pressundo-code-language">' . esc_html(strtoupper($language)) . '</span><textarea ' . $this->attrs($field) . ' rows="' . esc_attr((string) $rows) . '" spellcheck="false">' . esc_textarea((string) $value) . '</textarea></div>';
    }

    /** @param array<string,mixed> $field */
    private function attrs(array $field): string
    {
        $id = sanitize_key((string) ($field['id'] ?? $field['name'] ?? 'field'));
        $name = (string) ($field['name'] ?? $id);
        $attrs = 'id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="regular-text pressundo-field__control"';
        if (! empty($field['readonly'])) { $attrs .= ' readonly'; }
        if (! empty($field['disabled'])) { $attrs .= ' disabled'; }
        if (! empty($field['required'])) { $attrs .= ' required aria-required="true"'; }
        if (! empty($field['aria_describedby'])) { $attrs .= ' aria-describedby="' . esc_attr((string) $field['aria_describedby']) . '"'; }
        if (! empty($field['placeholder'])) { $attrs .= ' placeholder="' . esc_attr((string) $field['placeholder']) . '"'; }
        return $attrs;
    }

    /** @param array<string,mixed> $v */
    private function numberUnitPair(string $name, string $key, string $label, array $v, mixed $units, string $default, string $defaultUnit): string
    {
        $unitKey = $key . '_unit';
        $unitOptions = is_array($units) ? $this->normalizeOptions($units) : $this->units([]);
        return '<div class="pressundo-number-unit"><span class="pressundo-sub-label">' . esc_html($label) . '</span><div><input type="number" step="any" name="' . esc_attr($name . '[' . $key . ']') . '" value="' . esc_attr((string) ($v[$key] ?? $default)) . '">' . $this->subSelect($name, $unitKey, $v[$unitKey] ?? $defaultUnit, $unitOptions) . '</div></div>';
    }

    private function simpleSubInput(string $name, string $key, string $label, mixed $value, string $type = 'text', string $step = ''): string
    {
        return '<label><span class="pressundo-sub-label">' . esc_html($label) . '</span><input type="' . esc_attr($type) . '"' . ($step !== '' ? ' step="' . esc_attr($step) . '"' : '') . ' name="' . esc_attr($name . '[' . $key . ']') . '" value="' . esc_attr((string) $value) . '"></label>';
    }

    /** @param array<string|int,mixed> $options */
    private function subSelect(string $name, string $key, mixed $value, array $options): string
    {
        $html = '<select name="' . esc_attr($name . '[' . $key . ']') . '">';
        foreach ($options as $optionValue => $label) { $html .= '<option value="' . esc_attr((string) $optionValue) . '"' . selected((string) $value, (string) $optionValue, false) . '>' . esc_html((string) $label) . '</option>'; }
        return $html . '</select>';
    }

    /** @param array<string,mixed> $field @return array<string,string> */
    private function units(array $field): array
    {
        $units = $field['units'] ?? ['px','%','em','rem','vw','vh'];
        return $this->normalizeOptions(is_array($units) ? $units : ['px','%','em','rem']);
    }

    /** @param array<string|int,mixed> $options @return array<string,string> */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $key => $value) {
            if (is_int($key)) { $normalized[(string) $value] = strtoupper((string) $value); }
            else { $normalized[(string) $key] = is_scalar($value) ? (string) $value : (string) $key; }
        }
        return $normalized;
    }

    /** @return array<string,string> */
    private function fontWeights(): array
    {
        return ['100'=>'Thin 100','200'=>'Extra Light 200','300'=>'Light 300','400'=>'Regular 400','500'=>'Medium 500','600'=>'Semi Bold 600','700'=>'Bold 700','800'=>'Extra Bold 800','900'=>'Black 900'];
    }

    private function sanitizeDateTime(mixed $value): string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $value) ? $value : '';
    }

    /** @return list<int> */
    private function sanitizeIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        return array_values(array_unique(array_filter(array_map('absint', $values))));
    }

    /** @return array<string,mixed> */
    private function sanitizeTypography(mixed $value): array
    {
        $v = is_array($value) ? $value : [];
        return [
            'font_family' => sanitize_text_field((string) ($v['font_family'] ?? 'system-ui')),
            'font_weight' => preg_match('/^[1-9]00$/', (string) ($v['font_weight'] ?? '400')) ? (string) $v['font_weight'] : '400',
            'font_size' => $this->numeric($v['font_size'] ?? ''), 'font_size_unit' => $this->unit($v['font_size_unit'] ?? 'px'),
            'line_height' => $this->numeric($v['line_height'] ?? ''),
            'letter_spacing' => $this->numeric($v['letter_spacing'] ?? ''), 'letter_spacing_unit' => $this->unit($v['letter_spacing_unit'] ?? 'px'),
            'text_transform' => $this->allowed($v['text_transform'] ?? 'none', ['none','uppercase','lowercase','capitalize'], 'none'),
            'text_decoration' => $this->allowed($v['text_decoration'] ?? 'none', ['none','underline','line-through'], 'none'),
            'color' => $this->color($v['color'] ?? '#111827'),
        ];
    }

    /** @param list<string> $parts @return array<string,mixed> */
    private function sanitizeBoxValues(mixed $value, array $parts): array
    {
        $v = is_array($value) ? $value : [];
        $out = ['unit' => $this->unit($v['unit'] ?? 'px')];
        foreach ($parts as $part) { $out[$part] = $this->numericOrAuto($v[$part] ?? ''); }
        return $out;
    }

    /** @return array<string,mixed> */
    private function sanitizeBorder(mixed $value): array
    {
        $v = is_array($value) ? $value : [];
        return ['width'=>$this->numeric($v['width'] ?? '0'),'style'=>$this->allowed($v['style'] ?? 'solid',['none','solid','dashed','dotted','double'],'solid'),'color'=>$this->color($v['color'] ?? '#d0d5dd'),'unit'=>$this->unit($v['unit'] ?? 'px')];
    }

    /** @return array<string,mixed> */
    private function sanitizeGradient(mixed $value): array
    {
        $v = is_array($value) ? $value : [];
        return ['type'=>$this->allowed($v['type'] ?? 'linear',['linear','radial'],'linear'),'angle'=>$this->clamp($v['angle'] ?? 135,0,360),'start_color'=>$this->color($v['start_color'] ?? '#2563eb'),'start_position'=>$this->clamp($v['start_position'] ?? 0,0,100),'end_color'=>$this->color($v['end_color'] ?? '#7c3aed'),'end_position'=>$this->clamp($v['end_position'] ?? 100,0,100)];
    }

    /** @return array<string,mixed> */
    private function sanitizeBackground(mixed $value): array
    {
        $v = is_array($value) ? $value : [];
        return ['type'=>$this->allowed($v['type'] ?? 'color',['color','image','gradient'],'color'),'color'=>$this->color($v['color'] ?? '#ffffff'),'image'=>absint($v['image'] ?? 0),'position'=>$this->allowed($v['position'] ?? 'center center',['left top','center top','right top','left center','center center','right center','left bottom','center bottom','right bottom'],'center center'),'size'=>$this->allowed($v['size'] ?? 'cover',['auto','cover','contain'],'cover'),'repeat'=>$this->allowed($v['repeat'] ?? 'no-repeat',['no-repeat','repeat','repeat-x','repeat-y'],'no-repeat'),'attachment'=>$this->allowed($v['attachment'] ?? 'scroll',['scroll','fixed'],'scroll'),'gradient'=>$this->sanitizeGradient($v['gradient'] ?? [])];
    }

    /** @return array<string,mixed> */
    private function sanitizeShadow(mixed $value): array
    {
        $v = is_array($value) ? $value : [];
        return ['x'=>$this->numeric($v['x'] ?? 0),'y'=>$this->numeric($v['y'] ?? 4),'blur'=>$this->numeric($v['blur'] ?? 12),'spread'=>$this->numeric($v['spread'] ?? 0),'color'=>$this->color($v['color'] ?? '#000000'),'unit'=>$this->unit($v['unit'] ?? 'px'),'inset'=>! empty($v['inset'])];
    }

    /** @param array<string,mixed> $field @return array<string,mixed> */
    private function sanitizeResponsive(mixed $value, array $field): array
    {
        $v = is_array($value) ? $value : [];
        $type = (string) ($field['value_type'] ?? 'number');
        $clean = static fn (mixed $item): string => sanitize_text_field((string) $item);
        return ['desktop'=>$type === 'number' ? $this->numeric($v['desktop'] ?? '') : $clean($v['desktop'] ?? ''),'tablet'=>$type === 'number' ? $this->numeric($v['tablet'] ?? '') : $clean($v['tablet'] ?? ''),'mobile'=>$type === 'number' ? $this->numeric($v['mobile'] ?? '') : $clean($v['mobile'] ?? ''),'unit'=>$type === 'number' ? $this->unit($v['unit'] ?? ($field['default_unit'] ?? 'px')) : ''];
    }

    /** @return list<array{key:string,value:string}> */
    private function sanitizeKeyValue(mixed $value): array
    {
        $rows = is_array($value) ? $value : [];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) { continue; }
            $key = sanitize_text_field((string) ($row['key'] ?? ''));
            $val = sanitize_textarea_field((string) ($row['value'] ?? ''));
            if ($key === '' && $val === '') { continue; }
            $out[] = ['key'=>$key,'value'=>$val];
        }
        return $out;
    }

    private function sanitizeCode(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function numeric(mixed $value): string
    {
        $value = trim((string) $value);
        return $value !== '' && is_numeric($value) ? (string) (0 + $value) : '';
    }

    private function numericOrAuto(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === 'auto') { return 'auto'; }
        return $this->numeric($value);
    }

    private function unit(mixed $value): string
    {
        return $this->allowed($value, ['px','%','em','rem','vw','vh','vmin','vmax','ch','ex'], 'px');
    }

    /** @param list<string> $allowed */
    private function allowed(mixed $value, array $allowed, string $fallback): string
    {
        $value = sanitize_text_field((string) $value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function color(mixed $value): string
    {
        $color = sanitize_hex_color((string) $value);
        return $color ? strtoupper($color) : '#000000';
    }

    private function clamp(mixed $value, float $min, float $max): float|int
    {
        $num = is_numeric($value) ? (float) $value : $min;
        $num = max($min, min($max, $num));
        return fmod($num, 1.0) === 0.0 ? (int) $num : $num;
    }

    /** @param array<string,mixed> $v */
    private function gradientCss(array $v): string
    {
        $g = $this->sanitizeGradient($v);
        if ($g['type'] === 'radial') { return 'radial-gradient(circle, ' . $g['start_color'] . ' ' . $g['start_position'] . '%, ' . $g['end_color'] . ' ' . $g['end_position'] . '%)'; }
        return 'linear-gradient(' . $g['angle'] . 'deg, ' . $g['start_color'] . ' ' . $g['start_position'] . '%, ' . $g['end_color'] . ' ' . $g['end_position'] . '%)';
    }
}
