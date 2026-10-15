<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class EmailField extends AbstractField
{
    public function type(): string { return 'email'; }
    public function sanitize(mixed $value, array $field): string { return sanitize_email((string) $value); }
    public function render(array $field, mixed $value): string
    {
        return sprintf('<input type="email" %s value="%s">', $this->commonAttributes($field), esc_attr((string) $value));
    }
}
