<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

final class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        private readonly bool $valid,
        private readonly mixed $value,
        private readonly array $errors = [],
    ) {
    }

    public static function valid(mixed $value): self
    {
        return new self(true, $value, []);
    }

    /** @param list<string> $errors */
    public static function invalid(mixed $value, array $errors): self
    {
        return new self(false, $value, array_values($errors));
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
