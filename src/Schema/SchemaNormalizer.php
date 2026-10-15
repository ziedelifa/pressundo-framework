<?php

declare(strict_types=1);

namespace Pressundo\Framework\Schema;

use InvalidArgumentException;

final class SchemaNormalizer
{
    /** @param array<string,mixed> $schema @return array<string,mixed> */
    public function normalize(array $schema): array
    {
        $id = sanitize_key((string) ($schema['id'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('Schema id is required.');
        }

        $fields = [];
        foreach ((array) ($schema['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = sanitize_key((string) ($field['name'] ?? ''));
            $type = sanitize_key((string) ($field['type'] ?? 'text'));
            if ($name === '' || $type === '') {
                continue;
            }
            $field['name'] = $name;
            $field['id'] = sanitize_key((string) ($field['id'] ?? $name));
            $field['type'] = $type;
            $fields[] = $field;
        }

        $schema['id'] = $id;
        $schema['title'] = sanitize_text_field((string) ($schema['title'] ?? $id));
        $schema['fields'] = $fields;
        return $schema;
    }
}
