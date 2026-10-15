<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class LegacyDefaults
{
    /** @param array<string,mixed> $schema @return array<string,mixed> */
    public function fromSchema(array $schema): array
    {
        $fields = [];
        if (($schema['kind'] ?? '') === 'options') {
            foreach ((array) ($schema['sections'] ?? []) as $section) {
                if (is_array($section)) {
                    $fields = array_merge($fields, (array) ($section['fields'] ?? []));
                }
            }
        } else {
            $fields = (array) ($schema['fields'] ?? []);
        }

        return $this->fields($fields);
    }

    /** @param array<mixed> $fields @return array<string,mixed> */
    private function fields(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (($field['type'] ?? '') === 'group') {
                if (! empty($field['repeating'])) {
                    $values[$name] = is_array($field['default'] ?? null) ? $field['default'] : [];
                } else {
                    $values[$name] = $this->fields((array) ($field['fields'] ?? []));
                }
                continue;
            }

            $values[$name] = $field['default'] ?? null;
        }
        return $values;
    }
}
