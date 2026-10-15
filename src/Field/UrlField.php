<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

use Pressundo\Framework\Security\SecurityPolicy;

final class UrlField extends AbstractField
{
    public function type(): string { return 'url'; }
    public function sanitize(mixed $value, array $field): string { return SecurityPolicy::httpUrl($value); }
    public function render(array $field, mixed $value): string
    {
        return sprintf('<input type="url" %s value="%s">', $this->commonAttributes($field), esc_attr((string) $value));
    }
}
