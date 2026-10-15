<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class TimeField extends AbstractField
{
    public function type(): string { return 'time'; }
    public function sanitize(mixed $value, array $field): string
    {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) === 1 ? $value : '';
    }
    public function render(array $field, mixed $value): string
    {
        return sprintf('<input type="time" %s value="%s">', $this->commonAttributes($field), esc_attr((string) $value));
    }
}
