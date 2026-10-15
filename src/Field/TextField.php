<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

class TextField extends AbstractField
{
    public function type(): string { return 'text'; }

    public function sanitize(mixed $value, array $field): string
    {
        return sanitize_text_field((string) $value);
    }

    public function render(array $field, mixed $value): string
    {
        return sprintf('<input type="text" %s value="%s">', $this->commonAttributes($field), esc_attr((string) $value));
    }
}
