<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class CodeField extends AbstractField
{
    public function type(): string { return 'code'; }
    public function sanitize(mixed $value, array $field): string
    {
        return str_replace(["\r\n", "\r"], "\n", (string) $value);
    }
    public function render(array $field, mixed $value): string
    {
        return sprintf('<textarea %s rows="10" spellcheck="false">%s</textarea>', $this->commonAttributes($field), esc_textarea((string) $value));
    }
}
