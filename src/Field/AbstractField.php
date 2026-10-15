<?php

declare(strict_types=1);

namespace Pressundo\Framework\Field;

use Pressundo\Framework\Contracts\FieldTypeInterface;

abstract class AbstractField implements FieldTypeInterface
{
    /** @param array<string,mixed> $field */
    protected function commonAttributes(array $field): string
    {
        $id = sanitize_key((string) ($field['id'] ?? $field['name'] ?? 'field'));
        $name = (string) ($field['name'] ?? $id);
        $classes = trim('regular-text pressundo-field__control ' . (string) ($field['class'] ?? ''));
        $attributes = sprintf(
            'id="%s" name="%s" class="%s"',
            esc_attr($id),
            esc_attr($name),
            esc_attr($classes)
        );

        if (! empty($field['readonly'])) {
            $attributes .= ' readonly';
        }
        if (! empty($field['disabled'])) {
            $attributes .= ' disabled';
        }
        if (! empty($field['required'])) {
            $attributes .= ' required';
        }

        if (! empty($field['aria_describedby'])) {
            $attributes .= ' aria-describedby="' . esc_attr((string) $field['aria_describedby']) . '"';
        }
        if (! empty($field['required'])) {
            $attributes .= ' aria-required="true"';
        }

        if (isset($field['placeholder']) && is_scalar($field['placeholder'])) {
            $attributes .= ' placeholder="' . esc_attr((string) $field['placeholder']) . '"';
        }
        if (isset($field['autocomplete']) && is_scalar($field['autocomplete'])) {
            $attributes .= ' autocomplete="' . esc_attr((string) $field['autocomplete']) . '"';
        }

        return $attributes;
    }
}
