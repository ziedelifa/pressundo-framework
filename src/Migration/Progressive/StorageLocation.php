<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration\Progressive;

use InvalidArgumentException;

final class StorageLocation
{
    /** @param list<string|int> $path */
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly ?int $postId = null,
        public readonly array $path = [],
    ) {
        if (! in_array($this->type, ['option', 'post_meta'], true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal and are not rendered as HTML.
            throw new InvalidArgumentException(sprintf('Unsupported storage type "%s".', $this->type));
        }

        if ($this->key === '') {
            throw new InvalidArgumentException('Storage key cannot be empty.');
        }

        if ($this->type === 'post_meta' && (! is_int($this->postId) || $this->postId <= 0)) {
            throw new InvalidArgumentException('A positive post_id is required for post_meta storage.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type
            && $this->key === $other->key
            && $this->postId === $other->postId
            && $this->path === $other->path;
    }

    public function describe(): string
    {
        $base = $this->type === 'option'
            ? 'option:' . $this->key
            : sprintf('post:%d meta:%s', $this->postId, $this->key);

        if ($this->path === []) {
            return $base;
        }

        return $base . ' [' . implode('.', array_map('strval', $this->path)) . ']';
    }
}
