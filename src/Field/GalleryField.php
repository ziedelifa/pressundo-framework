<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

use Pressundo\Framework\Security\SecurityPolicy;

final class GalleryField extends AbstractField
{
    public function type(): string { return 'gallery'; }

    public function sanitize(mixed $value, array $field): array
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $return = ($field['return'] ?? 'id') === 'url' ? 'url' : 'id';
        $result = [];
        foreach ((array) $values as $item) {
            $item = $return === 'url' ? SecurityPolicy::httpUrl($item) : absint($item);
            if ($item !== '' && $item !== 0 && ! in_array($item, $result, true)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function render(array $field, mixed $value): string
    {
        $name = (string) ($field['name'] ?? $field['id'] ?? 'field');
        $serialized = implode(',', array_map('strval', is_array($value) ? $value : []));
        $return = (($field['return'] ?? 'id') === 'url') ? 'url' : 'id';
        $describedBy = ! empty($field['aria_describedby']) ? ' aria-describedby="' . esc_attr((string) $field['aria_describedby']) . '"' : '';
        return '<div class="pressundo-gallery-field" data-return="' . esc_attr($return) . '" data-pressundo-gallery-preview="1">'
            . '<div class="pressundo-gallery-preview" aria-live="polite" data-value="' . esc_attr($serialized) . '"></div>'
            . '<div class="pressundo-gallery-field__controls"><input type="text" id="' . esc_attr((string) ($field['id'] ?? 'field')) . '" name="' . esc_attr($name) . '" class="regular-text pressundo-field__control" value="' . esc_attr($serialized) . '"' . $describedBy . '>'
            . '<button type="button" class="button pressundo-gallery-select">' . esc_html__('Select gallery', 'pressundo-framework') . '</button>'
            . '<button type="button" class="button-link pressundo-gallery-clear"' . ($serialized === '' ? ' hidden' : '') . '>' . esc_html__('Clear', 'pressundo-framework') . '</button></div></div>';
    }
}
