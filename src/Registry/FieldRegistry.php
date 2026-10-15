<?php

declare(strict_types=1);

namespace Pressundo\Framework\Registry;

use InvalidArgumentException;
use Pressundo\Framework\Contracts\FieldTypeInterface;

final class FieldRegistry
{
    /** @var array<string,FieldTypeInterface> */
    private array $fields = [];

    public function register(FieldTypeInterface $field): void
    {
        $type = sanitize_key($field->type());
        if ($type === '') {
            throw new InvalidArgumentException('Field type cannot be empty.');
        }
        $this->fields[$type] = $field;
    }

    public function has(string $type): bool
    {
        return isset($this->fields[sanitize_key($type)]);
    }

    public function get(string $type): FieldTypeInterface
    {
        $type = sanitize_key($type);
        if (! isset($this->fields[$type])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal and are not rendered as HTML.
            throw new InvalidArgumentException(sprintf('Unknown field type "%s".', $type));
        }
        return $this->fields[$type];
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->fields);
    }
}
