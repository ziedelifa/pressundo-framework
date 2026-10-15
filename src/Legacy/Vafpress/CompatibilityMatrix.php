<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class CompatibilityMatrix
{
    public function __construct(private readonly ?MigrationOverrideRepository $overrides = null) {}
    /** @var array<string,array{target:string,status:string,notes:string}> */
    private const MAP = [
        'textbox' => ['target' => 'text', 'status' => 'automatic', 'notes' => 'Direct value mapping.'],
        'textarea' => ['target' => 'textarea', 'status' => 'automatic', 'notes' => 'Direct value mapping.'],
        'toggle' => ['target' => 'toggle', 'status' => 'automatic', 'notes' => 'Boolean-like value mapping.'],
        'select' => ['target' => 'select', 'status' => 'automatic', 'notes' => 'Static items map to options.'],
        'multiselect' => ['target' => 'multi_select', 'status' => 'automatic', 'notes' => 'Static items map to options.'],
        'radiobutton' => ['target' => 'radio', 'status' => 'automatic', 'notes' => 'Static items map to options.'],
        'radioimage' => ['target' => 'image_radio', 'status' => 'automatic', 'notes' => 'Image items are preserved.'],
        'checkbox' => ['target' => 'checkbox_group', 'status' => 'automatic', 'notes' => 'Vafpress checkbox is a multi-value field.'],
        'checkimage' => ['target' => 'image_checkbox_group', 'status' => 'automatic', 'notes' => 'Multi-value image choices are preserved.'],
        'slider' => ['target' => 'range', 'status' => 'automatic', 'notes' => 'Min/max/step values can be converted.'],
        'upload' => ['target' => 'media', 'status' => 'automatic', 'notes' => 'Legacy URL values can be retained.'],
        'date' => ['target' => 'date', 'status' => 'automatic', 'notes' => 'Date constraints and format are preserved as legacy metadata.'],
        'color' => ['target' => 'color', 'status' => 'automatic', 'notes' => 'Direct color value mapping.'],
        'codeeditor' => ['target' => 'code', 'status' => 'automatic', 'notes' => 'Legacy language/theme settings are retained where available.'],
        'html' => ['target' => 'html', 'status' => 'automatic', 'notes' => 'Static HTML requires contextual output review.'],
        'notebox' => ['target' => 'notice', 'status' => 'automatic', 'notes' => 'Presentation-only field.'],
        'fontawesome' => ['target' => 'icon', 'status' => 'automatic', 'notes' => 'Legacy fa-* identifiers are preserved as stable string values; the modern icon UI may map them visually without changing storage.'],
        'wpeditor' => ['target' => 'editor', 'status' => 'automatic', 'notes' => 'Migrates to WordPress editor-compatible field.'],
        'sorter' => ['target' => 'sortable_select', 'status' => 'automatic', 'notes' => 'Ordered selections are preserved and edited with the modern sortable control.'],
        'group' => ['target' => 'group', 'status' => 'automatic', 'notes' => 'Nested/repeating groups are handled recursively.'],
    ];

    /** @return array{target:string,status:string,notes:string} */
    public function forType(string $legacyType): array
    {
        $key = strtolower(trim($legacyType));
        if (isset(self::MAP[$key])) {
            return self::MAP[$key];
        }

        $override = $this->overrides?->fieldType($key);
        if (is_string($override) && $override !== '') {
            return [
                'target' => $override,
                'status' => 'automatic',
                'notes' => sprintf('Explicit migration override maps legacy field type "%s" to "%s".', $key, $override),
            ];
        }

        return [
            'target' => 'legacy_unknown',
            'status' => 'manual',
            'notes' => 'No automatic converter is registered for this Vafpress field type.',
        ];
    }

    /** @return array<string,array{target:string,status:string,notes:string}> */
    public function all(): array
    {
        return self::MAP;
    }
}
