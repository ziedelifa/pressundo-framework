<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

use Pressundo\Framework\Security\SecurityPolicy;

final class MediaField extends AbstractField
{
    public function type(): string { return 'media'; }

    public function sanitize(mixed $value, array $field): int|string
    {
        if (($field['return'] ?? 'id') === 'url') {
            return SecurityPolicy::httpUrl($value);
        }
        return absint($value);
    }

    public function render(array $field, mixed $value): string
    {
        $return = ($field['return'] ?? 'id') === 'url' ? 'url' : 'id';
        $inputType = $return === 'id' ? 'number' : 'url';
        $raw = is_scalar($value) ? (string) $value : '';
        return sprintf(
            '<div class="pressundo-media-field" data-return="%1$s" data-pressundo-media-preview="1"><div class="pressundo-media-preview" aria-live="polite" data-value="%2$s"></div><div class="pressundo-media-field__controls"><input type="%3$s" %4$s value="%2$s"><button type="button" class="button pressundo-media-select">%5$s</button><button type="button" class="button-link pressundo-media-clear"%6$s>%7$s</button></div></div>',
            esc_attr($return),
            esc_attr($raw),
            esc_attr($inputType),
            $this->commonAttributes($field),
            esc_html__('Select media', 'pressundo-framework'),
            $raw === '' ? ' hidden' : '',
            esc_html__('Clear', 'pressundo-framework')
        );
    }
}
