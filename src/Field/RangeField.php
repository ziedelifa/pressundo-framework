<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class RangeField extends AbstractField
{
    public function type(): string { return 'range'; }
    public function sanitize(mixed $value, array $field): int|float|string
    {
        if ($value === '' || $value === null || ! is_numeric($value)) { return ''; }
        $number = (float) $value;
        if (isset($field['min']) && is_numeric($field['min'])) { $number = max($number, (float) $field['min']); }
        if (isset($field['max']) && is_numeric($field['max'])) { $number = min($number, (float) $field['max']); }
        return floor($number) === $number ? (int) $number : $number;
    }
    public function render(array $field, mixed $value): string
    {
        $attrs = '';
        foreach (['min','max','step'] as $key) {
            if (isset($field[$key]) && is_numeric($field[$key])) { $attrs .= ' ' . $key . '="' . esc_attr((string) $field[$key]) . '"'; }
        }
        return sprintf(
            '<div class="pressundo-range-field"><input type="range" %s value="%s"%s><output class="pressundo-range-value">%s</output></div>',
            $this->commonAttributes($field),
            esc_attr(is_scalar($value) ? (string) $value : ''),
            $attrs,
            esc_html(is_scalar($value) ? (string) $value : '')
        );
    }
}
