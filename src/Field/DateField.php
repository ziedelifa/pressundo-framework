<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class DateField extends AbstractField
{
    public function type(): string { return 'date'; }
    public function sanitize(mixed $value, array $field): string
    {
        $value = sanitize_text_field((string) $value);
        if ($value === '') { return ''; }
        $parts = array_map('intval', explode('-', $value));
        return count($parts) === 3 && checkdate($parts[1], $parts[2], $parts[0]) ? $value : '';
    }
    public function render(array $field, mixed $value): string
    {
        return sprintf('<input type="date" %s value="%s">', $this->commonAttributes($field), esc_attr((string) $value));
    }
}
