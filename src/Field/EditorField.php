<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class EditorField extends AbstractField
{
    public function type(): string { return 'editor'; }
    public function sanitize(mixed $value, array $field): string { return wp_kses_post((string) $value); }
    public function render(array $field, mixed $value): string
    {
        return sprintf('<textarea %s rows="8">%s</textarea>', $this->commonAttributes($field), esc_textarea((string) $value));
    }
}
