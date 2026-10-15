<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class TemplateConverter
{
    public function __construct(
        private readonly CompatibilityMatrix $matrix = new CompatibilityMatrix(),
        private readonly DynamicItemResolver $dynamicItems = new DynamicItemResolver()
    ) {}

    /**
     * Convert a Vafpress option template or metabox template into the canonical Pressundo schema.
     * This method never writes to WordPress.
     *
     * @param array<mixed> $legacy
     * @return array{schema:array<string,mixed>,report:array<string,mixed>}
     */
    public function convert(array $legacy, string $schemaId = 'vafpress-import'): array
    {
        $report = [
            'automatic' => 0,
            'review' => 0,
            'manual' => 0,
            'fields' => [],
            'warnings' => [],
        ];

        if (isset($legacy['menus']) && is_array($legacy['menus'])) {
            $schema = $this->convertOptionTemplate($legacy, $schemaId, $report);
        } else {
            $schema = $this->convertMetaboxTemplate($legacy, $schemaId, $report);
        }

        return ['schema' => $schema, 'report' => $report];
    }

    /** @param array<string,mixed> $legacy @param array<string,mixed> $report @return array<string,mixed> */
    private function convertOptionTemplate(array $legacy, string $schemaId, array &$report): array
    {
        $sections = [];

        foreach ((array) ($legacy['menus'] ?? []) as $menuIndex => $menu) {
            if (! is_array($menu)) {
                continue;
            }

            $menuName = (string) ($menu['name'] ?? 'menu_' . $menuIndex);
            $menuTitle = (string) ($menu['title'] ?? $menuName);

            if (! empty($menu['menus']) && is_array($menu['menus'])) {
                foreach ($menu['menus'] as $subIndex => $submenu) {
                    if (! is_array($submenu)) {
                        continue;
                    }
                    $sections = array_merge($sections, $this->controlsToSections(
                        (array) ($submenu['controls'] ?? []),
                        (string) ($submenu['name'] ?? $menuName . '_submenu_' . $subIndex),
                        (string) ($submenu['title'] ?? $menuTitle),
                        $report,
                        ['menu' => $menuName, 'submenu' => (string) ($submenu['name'] ?? '')]
                    ));
                }
                continue;
            }

            $sections = array_merge($sections, $this->controlsToSections(
                (array) ($menu['controls'] ?? []),
                $menuName,
                $menuTitle,
                $report,
                ['menu' => $menuName]
            ));
        }

        return [
            'id' => $this->key($schemaId),
            'source' => 'vafpress',
            'kind' => 'options',
            'title' => (string) ($legacy['title'] ?? 'Imported Vafpress options'),
            'sections' => $sections,
            'legacy' => [
                'logo' => $legacy['logo'] ?? null,
            ],
        ];
    }

    /** @param array<mixed> $legacy @param array<string,mixed> $report @return array<string,mixed> */
    private function convertMetaboxTemplate(array $legacy, string $schemaId, array &$report): array
    {
        $fields = [];
        foreach ($legacy as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $fields[] = $this->convertField($field, 'field_' . $index, $report);
        }

        return [
            'id' => $this->key($schemaId),
            'source' => 'vafpress',
            'kind' => 'metabox',
            'title' => 'Imported Vafpress metabox',
            'fields' => $fields,
        ];
    }

    /** @param array<mixed> $controls @param array<string,mixed> $report @param array<string,string> $context @return list<array<string,mixed>> */
    private function controlsToSections(array $controls, string $fallbackName, string $fallbackTitle, array &$report, array $context): array
    {
        $sections = [];
        $looseFields = [];

        foreach ($controls as $index => $control) {
            if (! is_array($control)) {
                continue;
            }

            if (($control['type'] ?? '') === 'section') {
                $sectionFields = [];
                foreach ((array) ($control['fields'] ?? []) as $fieldIndex => $field) {
                    if (is_array($field)) {
                        $sectionFields[] = $this->convertField($field, 'field_' . $fieldIndex, $report);
                    }
                }

                $sections[] = [
                    'id' => $this->key((string) ($control['name'] ?? $fallbackName . '_section_' . $index)),
                    'title' => (string) ($control['title'] ?? $fallbackTitle),
                    'description' => (string) ($control['description'] ?? ''),
                    'conditions' => $this->convertDependency($control['dependency'] ?? null),
                    'show_if' => $this->convertSafeDependency($control['dependency'] ?? null),
                    'fields' => $sectionFields,
                    'legacy' => $context,
                ];
            } else {
                $looseFields[] = $this->convertField($control, 'field_' . $index, $report);
            }
        }

        if ($looseFields !== []) {
            array_unshift($sections, [
                'id' => $this->key($fallbackName),
                'title' => $fallbackTitle,
                'description' => '',
                'conditions' => [],
                'fields' => $looseFields,
                'legacy' => $context,
            ]);
        }

        return $sections;
    }

    /** @param array<string,mixed> $legacy @param array<string,mixed> $report @return array<string,mixed> */
    private function convertField(array $legacy, string $fallbackName, array &$report): array
    {
        $legacyType = strtolower((string) ($legacy['type'] ?? ''));
        $mapping = $this->matrix->forType($legacyType);
        $status = $mapping['status'];
        $warnings = [];

        if (isset($legacy['binding'])) {
            $status = $status === 'manual' ? 'manual' : 'review';
            $warnings[] = 'Field binding calls arbitrary legacy PHP and requires review.';
        }

        $itemResult = $this->convertItems($legacy['items'] ?? null, $warnings);
        $items = $itemResult['options'];
        if (! empty($itemResult['dynamic']) && empty($itemResult['resolved'])) {
            $status = $status === 'manual' ? 'manual' : 'review';
        }

        $name = (string) ($legacy['name'] ?? $fallbackName);
        $validationRules = $this->convertValidation($legacy['validation'] ?? '');
        $field = [
            'name' => $this->key($name),
            'type' => $mapping['target'],
            'label' => (string) ($legacy['label'] ?? ''),
            'description' => (string) ($legacy['description'] ?? ''),
            'default' => $legacy['default'] ?? null,
            'validation' => $validationRules,
            'conditions' => $this->convertDependency($legacy['dependency'] ?? null),
            'show_if' => $this->convertSafeDependency($legacy['dependency'] ?? null),
            'options' => $items,
            'legacy' => [
                'type' => $legacyType,
                'name' => $name,
                'binding' => $legacy['binding'] ?? null,
                'dynamic_items' => [
                    'dynamic' => ! empty($itemResult['dynamic']),
                    'resolved' => ! empty($itemResult['resolved']),
                    'source' => (string) ($itemResult['source'] ?? ''),
                    'message' => (string) ($itemResult['message'] ?? ''),
                ],
                'properties' => $this->legacyProperties($legacy),
            ],
            'migration' => [
                'status' => $status,
                'notes' => $mapping['notes'],
                'warnings' => $warnings,
            ],
        ];

        // Promote legacy validation rules so the modern renderer can expose
        // required/min/max semantics while the compatibility save service still
        // preserves Vafpress's original validation grammar.
        foreach ($validationRules as $rule) {
            if ($rule === 'required') { $field['required'] = true; continue; }
            if (preg_match('/^maxlength\[(\d+)\]$/', $rule, $m) === 1) { $field['max_length'] = (int) $m[1]; continue; }
            if (preg_match('/^minlength\[(\d+)\]$/', $rule, $m) === 1) { $field['min_length'] = (int) $m[1]; continue; }
            if (preg_match('/^maxselected\[(\d+)\]$/', $rule, $m) === 1) { $field['max_selection'] = (int) $m[1]; continue; }
            if (preg_match('/^minselected\[(\d+)\]$/', $rule, $m) === 1) { $field['min_selection'] = (int) $m[1]; continue; }
        }

        // Promote well-known Vafpress presentation/storage properties to native Pressundo fields.
        if ($legacyType === 'slider') {
            foreach (['min', 'max', 'step'] as $property) {
                if (isset($legacy[$property]) && is_numeric($legacy[$property])) { $field[$property] = $legacy[$property]; }
            }
        }
        if ($legacyType === 'upload') { $field['return'] = 'url'; }
        if ($legacyType === 'html') { $field['content'] = (string) ($legacy['content'] ?? $legacy['default'] ?? ''); }
        if ($legacyType === 'notebox') {
            $field['content'] = (string) ($legacy['content'] ?? $legacy['default'] ?? $legacy['description'] ?? '');
            $field['tone'] = (string) ($legacy['status'] ?? 'info');
        }

        if ($legacyType === 'group') {
            $children = [];
            foreach ((array) ($legacy['fields'] ?? []) as $index => $child) {
                if (is_array($child)) {
                    $children[] = $this->convertField($child, 'field_' . $index, $report);
                }
            }
            $field['fields'] = $children;
            $field['repeating'] = ! empty($legacy['repeating']);
            $field['migration']['structural_editable'] = $this->allChildrenAutomatic($children);
        }

        ++$report[$status];
        $report['fields'][] = [
            'name' => $field['name'],
            'legacy_type' => $legacyType,
            'target_type' => $field['type'],
            'status' => $status,
            'dynamic_source' => (string) ($itemResult['source'] ?? ''),
            'dynamic_resolved' => ! empty($itemResult['resolved']),
            'warnings' => $warnings,
        ];
        foreach ($warnings as $warning) {
            $report['warnings'][] = $field['name'] . ': ' . $warning;
        }

        return $field;
    }

    /** @return array{options:array<string,mixed>,dynamic:bool,resolved:bool,source:string,message:string} */
    private function convertItems(mixed $items, array &$warnings): array
    {
        if (! is_array($items)) {
            return ['options' => [], 'dynamic' => false, 'resolved' => true, 'source' => '', 'message' => ''];
        }

        $result = [];
        foreach ($items as $key => $item) {
            if ($key === 'data') {
                continue;
            }
            if (! is_array($item) || ! array_key_exists('value', $item)) {
                continue;
            }
            $value = (string) $item['value'];
            $result[$value] = [
                'label' => (string) ($item['label'] ?? $value),
                'image' => isset($item['img']) ? (string) $item['img'] : null,
            ];
        }

        $dynamic = $this->dynamicItems->resolve($items);
        if (! empty($dynamic['dynamic'])) {
            if (! empty($dynamic['resolved'])) {
                $result = array_replace($result, (array) ($dynamic['options'] ?? []));
            } else {
                $message = (string) ($dynamic['message'] ?? 'Dynamic Vafpress item source requires review.');
                if ($message !== '') {
                    $warnings[] = $message;
                }
            }
        }

        return [
            'options' => $result,
            'dynamic' => ! empty($dynamic['dynamic']),
            'resolved' => ! empty($dynamic['resolved']),
            'source' => (string) ($dynamic['source'] ?? ''),
            'message' => (string) ($dynamic['message'] ?? ''),
        ];
    }

    /** @param array<mixed> $children */
    private function allChildrenAutomatic(array $children): bool
    {
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['migration']['status'] ?? 'manual') !== 'automatic') {
                return false;
            }
            if (($child['type'] ?? '') === 'group' && ! $this->allChildrenAutomatic((array) ($child['fields'] ?? []))) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    private function convertDependency(mixed $dependency): array
    {
        if (! is_array($dependency)) {
            return [];
        }
        return [
            'legacy_function' => (string) ($dependency['function'] ?? ''),
            'field' => (string) ($dependency['field'] ?? ''),
            'mode' => 'legacy_callback',
        ];
    }

    /**
     * Translate the one built-in Vafpress dependency callback whose semantics
     * are deterministic and safe without executing arbitrary legacy PHP.
     * Custom callbacks intentionally remain migration-review boundaries.
     *
     * @return list<array{field:string,operator:string,value:mixed}>
     */
    private function convertSafeDependency(mixed $dependency): array
    {
        if (! is_array($dependency)) {
            return [];
        }
        if (strtolower(trim((string) ($dependency['function'] ?? ''))) !== 'vp_dep_boolean') {
            return [];
        }

        $raw = trim((string) ($dependency['field'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $rules = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $rawField) {
            $rawField = trim((string) $rawField);
            if ($rawField === '') {
                continue;
            }
            $rules[] = ['field' => $this->key($rawField), 'operator' => 'truthy', 'value' => true];
        }
        return $rules;
    }

    /** @return list<string> */
    private function convertValidation(mixed $validation): array
    {
        if (! is_string($validation) || trim($validation) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[|,]/', $validation) ?: [])));
    }

    /** @param array<string,mixed> $legacy @return array<string,mixed> */
    private function legacyProperties(array $legacy): array
    {
        $reserved = ['type', 'name', 'label', 'description', 'default', 'validation', 'dependency', 'binding', 'items', 'fields'];
        return array_diff_key($legacy, array_flip($reserved));
    }

    private function key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value) ?? '';
        return trim($value, '_-') ?: 'field';
    }
}
