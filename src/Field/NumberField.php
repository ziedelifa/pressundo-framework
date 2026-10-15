<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class NumberField extends AbstractField
{
    public function type(): string { return 'number'; }

    public function sanitize(mixed $value, array $field): int|float|string
    {
        if ($value === '' || $value === null || ! is_numeric($value)) {
            return '';
        }
        $number = (float) $value;
        return floor($number) === $number ? (int) $number : $number;
    }

    public function render(array $field, mixed $value): string
    {
        $attrs = '';
        foreach (['min','max','step'] as $key) {
            if (isset($field[$key]) && is_numeric($field[$key])) {
                $attrs .= ' ' . $key . '="' . esc_attr((string) $field[$key]) . '"';
            }
        }
        return sprintf('<input type="number" %s value="%s"%s>', $this->commonAttributes($field), esc_attr(is_scalar($value) ? (string) $value : ''), $attrs);
    }
}
