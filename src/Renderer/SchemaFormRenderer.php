<?php

declare(strict_types=1);

namespace Pressundo\Framework\Renderer;

use Pressundo\Framework\Registry\FieldRegistry;
use Pressundo\Framework\Api\OptionsResolver;
use Pressundo\Framework\Api\ConditionEvaluator;

final class SchemaFormRenderer
{
    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly OptionsResolver $optionsResolver,
        private readonly ConditionEvaluator $conditionEvaluator
    ) {}

    /**
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $values
     */
    public function render(array $schema, array $values = [], bool $disabled = false, string $namePrefix = '', bool $automaticOnly = false): string
    {
        $uiTheme = sanitize_key((string) ($schema['ui_theme'] ?? 'air')) ?: 'air';
        if (! in_array($uiTheme, ['air', 'slate', 'compact'], true)) { $uiTheme = 'air'; }
        $html = '<div class="pressundo-modern-form pressundo-ui--' . esc_attr($uiTheme) . '">';
        if ($disabled) {
            $html .= '<fieldset disabled>';
        }

        if (in_array((string) ($schema['kind'] ?? ''), ['options', 'metabox'], true) && (array) ($schema['sections'] ?? []) !== []) {
            foreach ((array) ($schema['sections'] ?? []) as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $sectionId = (string) ($section['id'] ?? 'section');
                $schemaId = sanitize_key((string) ($schema['id'] ?? ''));
                $sectionDomId = ((string) ($schema['kind'] ?? '') === 'metabox' && $schemaId !== '')
                    ? 'pressundo-section-' . $schemaId . '-' . $sectionId
                    : 'pressundo-section-' . $sectionId;
                $sectionTitle = $this->nonEmpty((string) ($section['title'] ?? ''), $this->humanize($sectionId));
                $sectionVisible = $this->conditionEvaluator->visible($section, $values);
                $sectionConditionData = $this->conditionData($section);
                $sectionConditionClass = $sectionVisible ? '' : ' is-condition-hidden';
                $sectionConditionAttr = $sectionConditionData !== '' ? ' data-pressundo-conditions="' . esc_attr($sectionConditionData) . '"' : '';
                $html .= '<section id="' . esc_attr($sectionDomId) . '" class="pressundo-modern-section' . $sectionConditionClass . '" data-section-id="' . esc_attr($sectionId) . '"' . $sectionConditionAttr . '>';
                $html .= '<div class="pressundo-modern-section__heading"><h5>' . esc_html($sectionTitle) . '</h5>';
                if (trim((string) ($section['description'] ?? '')) !== '') {
                    $html .= '<p>' . esc_html((string) $section['description']) . '</p>';
                }
                $html .= '</div>';
                $html .= $this->renderFields((array) ($section['fields'] ?? []), $values, $namePrefix, $automaticOnly);
                $html .= '</section>';
            }
        } else {
            $html .= $this->renderFields((array) ($schema['fields'] ?? []), $values, $namePrefix, $automaticOnly);
        }

        if ($disabled) {
            $html .= '</fieldset>';
        }
        return $html . '</div>';
    }

    /** @param array<mixed> $fields @param array<string,mixed> $values */
    private function renderFields(array $fields, array $values, string $namePrefix, bool $automaticOnly): string
    {
        $html = '<div class="pressundo-modern-fields">';
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = $this->nonEmpty((string) ($field['name'] ?? ''), 'field');
            $type = $this->nonEmpty((string) ($field['type'] ?? ''), 'text');
            $label = $this->nonEmpty((string) ($field['label'] ?? ''), $this->humanize($name));
            $description = trim((string) ($field['description'] ?? ''));
            $value = array_key_exists($name, $values) ? $values[$name] : ($field['default'] ?? null);
            $path = $this->fieldPath($namePrefix, $name);
            $migrationStatus = (string) ($field['migration']['status'] ?? 'automatic');
            $presentationOnly = in_array($type, ['html', 'notice', 'heading', 'divider'], true);
            $locked = $automaticOnly && ($migrationStatus !== 'automatic' || $presentationOnly);
            $visible = $this->conditionEvaluator->visible($field, $values);
            $conditionData = $this->conditionData($field);
            $conditionClass = $visible ? '' : ' is-condition-hidden';
            $conditionAttr = $conditionData !== '' ? ' data-pressundo-conditions="' . esc_attr($conditionData) . '"' : '';

            $fieldId = $this->fieldId($path);
            $descriptionId = $fieldId . '-description';
            $requiredMark = ! empty($field['required']) ? '<span class="pressundo-required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__('Required', 'pressundo-framework') . '</span>' : '';
            $html .= '<div class="pressundo-modern-field' . ($presentationOnly ? ' is-presentation-field' : '') . ($locked ? ' is-shadow-locked' : '') . $conditionClass . '" data-pressundo-field="' . esc_attr($name) . '" data-pressundo-type="' . esc_attr($type) . '"' . $conditionAttr . '>';
            $html .= '<div class="pressundo-modern-field__label"><label for="' . esc_attr($fieldId) . '">' . esc_html($label) . $requiredMark . '</label><span class="pressundo-field-type">' . esc_html($type) . '</span></div>';
            $html .= '<div class="pressundo-modern-field__control">';
            if ($locked) {
                $html .= '<fieldset disabled class="pressundo-shadow-field-lock">';
            }

            if ($type === 'group') {
                $html .= $this->renderGroup($field, $value, $path, $automaticOnly);
            } elseif ($this->registry->has($type)) {
                $renderField = $field;
                if (isset($renderField['options']) || isset($renderField['options_callback'])) {
                    $renderField['options'] = $this->optionsResolver->resolve($renderField);
                }
                $renderField['name'] = $path !== '' ? $path : $name;
                $renderField['id'] = $this->fieldId($path !== '' ? $path : $name);
                if ($description !== '') {
                    $renderField['aria_describedby'] = $descriptionId;
                }
                $html .= $this->registry->get($type)->render($renderField, $value);
            } else {
                $html .= $this->fallbackControl($field, $value, $path);
            }

            if ($locked) {
                /* translators: %s: migration status name. */
                $lockReason = $presentationOnly ? __('presentation-only control', 'pressundo-framework') : sprintf(__('migration status is %s', 'pressundo-framework'), $migrationStatus);
                /* translators: %s: reason the field is locked. */
                $html .= '</fieldset><p class="pressundo-shadow-lock-note">' . esc_html(sprintf(__('Locked in shadow mode: %s.', 'pressundo-framework'), $lockReason)) . '</p>';
            }
            if ($description !== '') {
                $html .= '<p id="' . esc_attr($descriptionId) . '" class="description pressundo-field-description">' . esc_html($description) . '</p>';
            }
            $html .= '<p class="pressundo-field-error" role="alert" hidden></p>';
            $html .= '</div></div>';
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $field */
    private function renderGroup(array $field, mixed $value, string $path, bool $automaticOnly): string
    {
        $children = (array) ($field['fields'] ?? []);
        $repeating = ! empty($field['repeating']);
        $html = '<div class="pressundo-modern-group">';

        if (! $repeating) {
            $html .= $this->renderFields($children, is_array($value) ? $value : [], $path, $automaticOnly);
            return $html . '</div>';
        }

        $rawRows = is_array($value) ? $value : [];
        // Submitted shadow forms contain a presence marker alongside numeric rows.
        // It is form bookkeeping, not a repeater row.
        unset($rawRows['__pressundo_repeater_present']);
        $rows = array_values($rawRows);
        $migration = is_array($field['migration'] ?? null) ? $field['migration'] : [];
        $structuralFlag = array_key_exists('structural_editable', $migration) ? ! empty($migration['structural_editable']) : true;
        $structuralEditable = $structuralFlag && $this->repeaterRowsMatchSchema($rows, $children);
        if ($rows === []) {
            $rows = [[]];
        }

        $minRows = isset($field['min_rows']) ? max(0, (int) $field['min_rows']) : 0;
        $maxRows = isset($field['max_rows']) ? max(0, (int) $field['max_rows']) : 0;
        $html .= '<div class="pressundo-repeater" data-pressundo-repeater="1" data-name-prefix="' . esc_attr($path) . '" data-structural-editable="' . ($structuralEditable ? '1' : '0') . '" data-min-rows="' . esc_attr((string) $minRows) . '" data-max-rows="' . esc_attr((string) $maxRows) . '">';
        $html .= '<input type="hidden" class="pressundo-repeater-present" name="' . esc_attr($path . '[__pressundo_repeater_present]') . '" value="1">';
        $html .= '<div class="pressundo-repeater__rows">';
        foreach ($rows as $index => $row) {
            $rowValues = is_array($row) ? $row : [];
            $rowKey = isset($rowValues['__pressundo_row_key']) ? sanitize_text_field((string) $rowValues['__pressundo_row_key']) : (string) $index;
            unset($rowValues['__pressundo_row_key']);
            $html .= $this->renderRepeaterRow($children, $rowValues, $path, (string) $index, $automaticOnly, $structuralEditable, $rowKey);
        }
        $html .= '</div>';

        if ($structuralEditable) {
            $html .= '<div class="pressundo-repeater__footer"><button type="button" class="button pressundo-repeater-add">' . esc_html__('Add row', 'pressundo-framework') . '</button></div>';
            $html .= '<template class="pressundo-repeater-template">';
            $html .= $this->renderRepeaterRow($children, [], $path, '__INDEX__', $automaticOnly, true, '__NEW__');
            $html .= '</template>';
        } else {
            $html .= '<p class="pressundo-repeater__structure-note">' . esc_html__('Row add/remove/reorder is locked because the repeater contains review/manual fields or legacy keys not represented by the normalized schema.', 'pressundo-framework') . '</p>';
        }

        return $html . '</div></div>';
    }

    /** @param array<mixed> $children @param array<string,mixed> $rowValues */
    private function renderRepeaterRow(array $children, array $rowValues, string $path, string $index, bool $automaticOnly, bool $structuralEditable, string $rowKey): string
    {
        $rowPath = $path . '[' . $index . ']';
        $displayIndex = ctype_digit($index) ? ((int) $index) + 1 : 0;
        $summary = $this->repeaterSummary($children, $rowValues);
        /* translators: %d: repeater item number. */
        $title = $summary !== '' ? $summary : ($displayIndex > 0 ? sprintf(__('Item %d', 'pressundo-framework'), $displayIndex) : __('New item', 'pressundo-framework'));
        $html = '<div class="pressundo-repeater-row" data-row-key="' . esc_attr($rowKey) . '" draggable="true">';
        $html .= '<input type="hidden" class="pressundo-repeater-row-key" name="' . esc_attr($rowPath . '[__pressundo_row_key]') . '" value="' . esc_attr($rowKey) . '">';
        $html .= '<div class="pressundo-repeater-row__title">';
        if ($structuralEditable) {
            $html .= '<button type="button" class="pressundo-icon-button pressundo-drag-handle pressundo-repeater-drag" aria-label="' . esc_attr__('Drag to reorder item', 'pressundo-framework') . '" title="' . esc_attr__('Drag to reorder', 'pressundo-framework') . '"><span aria-hidden="true">⋮⋮</span></button>';
        }
        $html .= '<button type="button" class="pressundo-repeater-collapse" aria-expanded="true"><span class="pressundo-repeater-row__name">' . esc_html($title) . '</span><span class="pressundo-repeater-chevron" aria-hidden="true">⌄</span></button>';
        if ($structuralEditable) {
            $html .= '<details class="pressundo-row-menu"><summary aria-label="' . esc_attr__('Item actions', 'pressundo-framework') . '">•••</summary><div class="pressundo-row-menu__panel">';
            $html .= '<button type="button" class="pressundo-row-menu__action pressundo-repeater-duplicate">' . esc_html__('Duplicate', 'pressundo-framework') . '</button>';
            $html .= '<button type="button" class="pressundo-row-menu__action pressundo-repeater-up">' . esc_html__('Move up', 'pressundo-framework') . '</button>';
            $html .= '<button type="button" class="pressundo-row-menu__action pressundo-repeater-down">' . esc_html__('Move down', 'pressundo-framework') . '</button>';
            $html .= '<button type="button" class="pressundo-row-menu__action is-danger pressundo-repeater-remove">' . esc_html__('Delete', 'pressundo-framework') . '</button>';
            $html .= '</div></details>';
        }
        $html .= '</div><div class="pressundo-repeater-row__body">';
        $html .= $this->renderFields($children, $rowValues, $rowPath, $automaticOnly);
        return $html . '</div></div>';
    }


    /** @param array<mixed> $children @param array<string,mixed> $rowValues */
    private function repeaterSummary(array $children, array $rowValues): string
    {
        foreach ($children as $child) {
            if (! is_array($child)) { continue; }
            $name = (string) ($child['name'] ?? '');
            if ($name === '' || ! array_key_exists($name, $rowValues)) { continue; }
            $value = $rowValues[$name];
            if (is_scalar($value) && trim((string) $value) !== '') {
                return wp_strip_all_tags((string) $value);
            }
        }
        return '';
    }

    /** @param array<mixed> $rows @param array<mixed> $children */
    private function repeaterRowsMatchSchema(array $rows, array $children): bool
    {
        $known = [];
        foreach ($children as $child) {
            if (is_array($child) && ! empty($child['name'])) {
                $known[] = (string) $child['name'];
            }
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }
            foreach (array_keys($row) as $key) {
                if ((string) $key === '__pressundo_row_key') {
                    continue;
                }
                if (! in_array((string) $key, $known, true)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @param array<string,mixed> $field */
    private function fallbackControl(array $field, mixed $value, string $path): string
    {
        $type = (string) ($field['type'] ?? 'legacy_unknown');
        $name = $path !== '' ? $path : (string) ($field['name'] ?? 'field');
        $id = $this->fieldId($name);

        if (in_array($type, ['color', 'date', 'range', 'media', 'icon'], true)) {
            $inputType = match ($type) {
                'color' => 'color',
                'date' => 'date',
                'range' => 'range',
                default => 'text',
            };
            $attributes = '';
            if ($type === 'range') {
                $properties = (array) ($field['legacy']['properties'] ?? []);
                foreach (['min', 'max', 'step'] as $attribute) {
                    if (isset($properties[$attribute]) && is_numeric($properties[$attribute])) {
                        $attributes .= ' ' . $attribute . '="' . esc_attr((string) $properties[$attribute]) . '"';
                    }
                }
            }
            return '<input type="' . esc_attr($inputType) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="regular-text pressundo-field__control" value="' . esc_attr(is_scalar($value) ? (string) $value : '') . '"' . $attributes . '>';
        }
        if ($type === 'editor' || $type === 'code') {
            return '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="large-text pressundo-field__control" rows="6">' . esc_textarea(is_scalar($value) ? (string) $value : '') . '</textarea>';
        }
        if (in_array($type, ['radio', 'checkbox_group', 'multi_select', 'image_radio', 'image_checkbox_group', 'sortable_select'], true)) {
            $options = (array) ($field['options'] ?? []);
            if ($options === []) {
                return '<span class="pressundo-control-placeholder">' . esc_html__('No static choices available in dry-run.', 'pressundo-framework') . '</span>';
            }
            $selected = is_array($value) ? array_map('strval', $value) : [(string) $value];

            if ($type === 'sortable_select') {
                $properties = (array) ($field['legacy']['properties'] ?? []);
                $maxSelection = isset($properties['max_selection']) && is_numeric($properties['max_selection']) ? max(0, (int) $properties['max_selection']) : 0;
                $html = '<div class="pressundo-sortable-select" data-pressundo-sortable="1" data-field-name="' . esc_attr($name) . '" data-max-selection="' . esc_attr((string) $maxSelection) . '"><ol>';
                foreach ($selected as $selectedKey) {
                    if ($selectedKey === '' || ! array_key_exists($selectedKey, $options)) {
                        continue;
                    }
                    $option = $options[$selectedKey];
                    $label = is_array($option) ? (string) ($option['label'] ?? $selectedKey) : (string) $option;
                    $html .= '<li data-value="' . esc_attr($selectedKey) . '" data-label="' . esc_attr($label) . '" draggable="true"><button type="button" class="button-link pressundo-drag-handle pressundo-sortable-drag" aria-label="' . esc_attr__('Drag to reorder item', 'pressundo-framework') . '">↕</button><span>' . esc_html($label) . '</span>';
                    $html .= '<input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($selectedKey) . '">';
                    $html .= '<span class="pressundo-sortable-select__actions"><button type="button" class="button-link pressundo-sortable-up" aria-label="' . esc_attr__('Move up', 'pressundo-framework') . '">↑</button><button type="button" class="button-link pressundo-sortable-down" aria-label="' . esc_attr__('Move down', 'pressundo-framework') . '">↓</button><button type="button" class="button-link-delete pressundo-sortable-remove">' . esc_html__('Remove', 'pressundo-framework') . '</button></span></li>';
                }
                $html .= '</ol><div class="pressundo-sortable-select__available"><select class="pressundo-sortable-available"><option value="">' . esc_html__('Choose an item…', 'pressundo-framework') . '</option>';
                foreach ($options as $key => $option) {
                    if (in_array((string) $key, $selected, true)) {
                        continue;
                    }
                    $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
                    $html .= '<option value="' . esc_attr((string) $key) . '">' . esc_html($label) . '</option>';
                }
                $html .= '</select><button type="button" class="button pressundo-sortable-add">' . esc_html__('Add', 'pressundo-framework') . '</button></div></div>';
                return $html;
            }

            if (in_array($type, ['multi_select', 'checkbox_group', 'image_checkbox_group'], true)) {
                $html = '<input type="hidden" name="' . esc_attr($name) . '[]" value=""><select multiple id="' . esc_attr($id) . '" name="' . esc_attr($name) . '[]" class="pressundo-field__control">';
                foreach ($options as $key => $option) {
                    $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
                    $html .= '<option value="' . esc_attr((string) $key) . '"' . selected(in_array((string) $key, $selected, true), true, false) . '>' . esc_html($label) . '</option>';
                }
                return $html . '</select>';
            }

            $html = '<div class="pressundo-choice-preview">';
            foreach (array_slice($options, 0, 24, true) as $key => $option) {
                $label = is_array($option) ? (string) ($option['label'] ?? $key) : (string) $option;
                $html .= '<label><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr((string) $key) . '"' . checked((string) $value, (string) $key, false) . '> ' . esc_html($label) . '</label>';
            }
            return $html . '</div>';
        }
        if (in_array($type, ['html', 'notice'], true)) {
            return '<span class="pressundo-control-placeholder">' . esc_html__('Presentation-only legacy control.', 'pressundo-framework') . '</span>';
        }
        /* translators: %s: field type identifier. */
        return '<span class="pressundo-control-placeholder pressundo-control-placeholder--manual">' . esc_html(sprintf(__('Renderer for “%s” will require migration support.', 'pressundo-framework'), $type)) . '</span>';
    }

    /** @param array<string,mixed> $field */
    private function conditionData(array $field): string
    {
        $show = $this->conditionEvaluator->normalize($field['show_if'] ?? []);
        $hide = $this->conditionEvaluator->normalize($field['hide_if'] ?? []);
        if ($show === [] && $hide === []) {
            return '';
        }
        $json = wp_json_encode([
            'show' => $show,
            'hide' => $hide,
            'relation' => strtolower((string) ($field['condition_relation'] ?? 'and')) === 'or' ? 'or' : 'and',
        ]);
        return is_string($json) ? $json : '';
    }

    private function fieldPath(string $prefix, string $name): string
    {
        return $prefix === '' ? $name : $prefix . '[' . $name . ']';
    }

    private function fieldId(string $path): string
    {
        return 'pressundo-field-' . substr(sha1($path), 0, 12);
    }

    private function nonEmpty(string $value, string $fallback): string
    {
        return trim($value) !== '' ? $value : $fallback;
    }

    private function humanize(string $value): string
    {
        $value = trim(str_replace(['_', '-'], ' ', $value));
        return $value !== '' ? ucwords($value) : 'Field';
    }
}
