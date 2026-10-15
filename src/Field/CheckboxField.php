<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

class CheckboxField extends AbstractField
{
    public function type(): string { return 'checkbox'; }

    public function sanitize(mixed $value, array $field): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function render(array $field, mixed $value): string
    {
        $id = sanitize_key((string) ($field['id'] ?? $field['name'] ?? 'field'));
        $name = (string) ($field['name'] ?? $id);
        $disabled = ! empty($field['disabled']) || ! empty($field['readonly']) ? ' disabled' : '';
        $required = ! empty($field['required']) ? ' required' : '';
        return sprintf('<input type="hidden" name="%2$s" value="0"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s%5$s>', esc_attr($id), esc_attr($name), checked((bool) $value, true, false), $disabled, $required);
    }
}
