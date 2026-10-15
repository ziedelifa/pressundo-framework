<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

final class NoticeField extends AbstractField
{
    public function type(): string { return 'notice'; }
    public function sanitize(mixed $value, array $field): string { return (string) $value; }
    public function render(array $field, mixed $value): string
    {
        $content = (string) ($field['content'] ?? $field['default'] ?? $value ?? '');
        $tone = sanitize_key((string) ($field['tone'] ?? 'info'));
        if (! in_array($tone, ['info','success','warning','danger'], true)) { $tone = 'info'; }
        return '<div class="pressundo-notice-field pressundo-notice-field--' . esc_attr($tone) . '">' . wp_kses_post($content) . '</div>';
    }
}
