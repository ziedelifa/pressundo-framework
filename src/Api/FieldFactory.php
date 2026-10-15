<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

use InvalidArgumentException;

final class FieldFactory
{
    /** @return array<string,mixed> */
    public function make(string $type, string $id, array $args = []): array
    {
        $type = sanitize_key($type);
        $id = sanitize_key($id);

        if ($type === '') {
            throw new InvalidArgumentException('Field type cannot be empty.');
        }
        if ($id === '') {
            throw new InvalidArgumentException('Field id cannot be empty.');
        }

        $field = array_replace([
            'name' => $id,
            'id' => $id,
            'type' => $type,
            'label' => $this->humanize($id),
            'default' => null,
            'description' => '',
            'required' => false,
            'disabled' => false,
            'readonly' => false,
            'show_if' => [],
            'hide_if' => [],
            'condition_relation' => 'and',
        ], $args);

        // The public identifiers are canonical and cannot be replaced accidentally by args.
        $field['name'] = $id;
        $field['id'] = $id;
        $field['type'] = $type;
        $field['label'] = trim((string) ($field['label'] ?? '')) !== ''
            ? (string) $field['label']
            : $this->humanize($id);

        if (function_exists('apply_filters')) {
            /** @var array<string,mixed> $field */
            $field = apply_filters('pressundo_field_definition', $field, $type, $id, $args);
            /** @var array<string,mixed> $field */
            $field = apply_filters('pressundo_field_definition_' . $type, $field, $id, $args);
        }

        // Keep public identifiers stable even when presentation filters are used.
        $field['name'] = $id;
        $field['id'] = $id;
        $field['type'] = $type;

        return $field;
    }

    /** @param list<array<string,mixed>> $fields @return array<string,mixed> */
    public function group(string $id, array $fields, array $args = []): array
    {
        $args['fields'] = $fields;
        $args['repeating'] = false;
        return $this->make('group', $id, $args);
    }

    /** @param list<array<string,mixed>> $fields @return array<string,mixed> */
    public function repeater(string $id, array $fields, array $args = []): array
    {
        $args['fields'] = $fields;
        $args['repeating'] = true;
        $args = array_replace(['min_rows' => 0, 'max_rows' => 0], $args);
        return $this->make('group', $id, $args);
    }

    /** @param list<array<string,mixed>> $fields @return array<string,mixed> */
    public function section(string $id, array $fields, array $args = []): array
    {
        $id = sanitize_key($id);
        if ($id === '') {
            throw new InvalidArgumentException('Section id cannot be empty.');
        }

        $section = array_replace([
            'id' => $id,
            'title' => $this->humanize($id),
            'description' => '',
            'fields' => $fields,
        ], $args);
        $section['id'] = $id;
        $section['fields'] = $fields;

        if (function_exists('apply_filters')) {
            /** @var array<string,mixed> $section */
            $section = apply_filters('pressundo_section_definition', $section, $id, $fields, $args);
        }

        return $section;
    }

    private function humanize(string $id): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $id));
    }
}
