<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class TextareaField extends AbstractField
{
    public function type(): string { return 'textarea'; }

    public function sanitize(mixed $value, array $field): string
    {
        return sanitize_textarea_field((string) $value);
    }

    public function render(array $field, mixed $value): string
    {
        return sprintf('<textarea %s rows="5">%s</textarea>', $this->commonAttributes($field), esc_textarea((string) $value));
    }
}
