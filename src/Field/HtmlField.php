<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class HtmlField extends AbstractField
{
    public function type(): string { return 'html'; }
    public function sanitize(mixed $value, array $field): string { return (string) $value; }
    public function render(array $field, mixed $value): string
    {
        $content = (string) ($field['content'] ?? $field['default'] ?? $value ?? '');
        return '<div class="pressundo-html-field">' . wp_kses_post($content) . '</div>';
    }
}
