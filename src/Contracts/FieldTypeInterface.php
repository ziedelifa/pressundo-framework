<?php

declare(strict_types=1);

namespace Pressundo\Framework\Contracts;

interface FieldTypeInterface
{
    public function type(): string;

    /** @param array<string,mixed> $field */
    public function sanitize(mixed $value, array $field): mixed;

    /** @param array<string,mixed> $field */
    public function render(array $field, mixed $value): string;
}
