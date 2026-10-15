<?php

declare(strict_types=1);

namespace Pressundo\Framework\Renderer;

final class SchemaPreviewRenderer
{
    /** @param array<string,mixed> $schema */
    public function render(array $schema): string
    {
        $kind = $this->nonEmpty((string) ($schema['kind'] ?? ''), 'schema');
        $title = $this->nonEmpty((string) ($schema['title'] ?? ''), $this->humanize((string) ($schema['id'] ?? 'Schema')));
        $html = '<div class="pressundo-schema-preview">';
        $html .= '<div class="pressundo-schema-preview__head"><div><span class="pressundo-kicker">' . esc_html($kind) . '</span><h4>' . esc_html($title) . '</h4></div></div>';

        if ($kind === 'options') {
            foreach ((array) ($schema['sections'] ?? []) as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $sectionId = (string) ($section['id'] ?? 'section');
                $sectionTitle = $this->nonEmpty((string) ($section['title'] ?? ''), $this->humanize($sectionId));
                $html .= '<section class="pressundo-schema-section">';
                $html .= '<h5>' . esc_html($sectionTitle) . '</h5>';
                $description = trim((string) ($section['description'] ?? ''));
                if ($description !== '') {
                    $html .= '<p>' . esc_html($description) . '</p>';
                }
                $html .= $this->renderFields((array) ($section['fields'] ?? []));
                $html .= '</section>';
            }
        } else {
            $html .= $this->renderFields((array) ($schema['fields'] ?? []));
        }

        return $html . '</div>';
    }

    /** @param array<mixed> $fields */
    private function renderFields(array $fields): string
    {
        $html = '<div class="pressundo-preview-fields">';
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $status = $this->nonEmpty((string) ($field['migration']['status'] ?? ''), 'automatic');
            $name = $this->nonEmpty((string) ($field['name'] ?? ''), 'field');
            $type = $this->nonEmpty((string) ($field['type'] ?? ''), 'text');
            $label = $this->nonEmpty((string) ($field['label'] ?? ''), $this->humanize($name));
            $html .= '<div class="pressundo-preview-field">';
            $html .= '<div class="pressundo-preview-field__meta"><strong>' . esc_html($label) . '</strong><code>' . esc_html($name) . '</code></div>';
            $html .= '<div class="pressundo-preview-field__type"><span class="pressundo-status pressundo-status--' . esc_attr($status) . '">' . esc_html($status) . '</span><span>' . esc_html($type) . '</span></div>';

            $dynamic = (array) ($field['legacy']['dynamic_items'] ?? []);
            if (! empty($dynamic['dynamic'])) {
                $dynamicSource = trim((string) ($dynamic['source'] ?? ''));
                $dynamicMessage = trim((string) ($dynamic['message'] ?? ''));
                if (! empty($dynamic['resolved'])) {
                    $dynamicLabel = $dynamicSource !== ''
                        ? sprintf(
                            /* translators: %s: legacy datasource function name. */
                            __('Datasource resolved safely: %s', 'pressundo-framework'),
                            $dynamicSource
                        )
                        : ($dynamicMessage !== '' ? $dynamicMessage : __('Datasource resolved safely.', 'pressundo-framework'));
                } else {
                    $dynamicLabel = $dynamicSource !== ''
                        ? sprintf(
                            /* translators: %s: legacy datasource function name. */
                            __('Datasource requires review: %s', 'pressundo-framework'),
                            $dynamicSource
                        )
                        : ($dynamicMessage !== '' ? $dynamicMessage : __('Datasource requires review.', 'pressundo-framework'));
                }
                $html .= '<p class="pressundo-datasource-note">' . esc_html($dynamicLabel) . '</p>';
            }

            if ($type === 'group') {
                $mode = ! empty($field['repeating']) ? __('Repeater', 'pressundo-framework') : __('Group', 'pressundo-framework');
                if (! empty($field['repeating']) && ! empty($field['migration']['structural_editable'])) {
                    $mode .= ' · ' . __('add/remove/reorder ready', 'pressundo-framework');
                }
                $html .= '<div class="pressundo-preview-group"><div class="pressundo-preview-group__meta">' . esc_html($mode) . '</div>' . $this->renderFields((array) ($field['fields'] ?? [])) . '</div>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
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
