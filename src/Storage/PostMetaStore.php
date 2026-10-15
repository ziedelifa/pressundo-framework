<?php

declare(strict_types=1);

namespace Pressundo\Framework\Storage;

final class PostMetaStore
{
    public function get(int $postId, string $key, mixed $default = null): mixed
    {
        $value = get_post_meta($postId, $key, true);
        return $value === '' ? $default : $value;
    }

    public function set(int $postId, string $key, mixed $value): bool
    {
        return false !== update_post_meta($postId, $key, $value);
    }

    public function delete(int $postId, string $key): bool
    {
        return delete_post_meta($postId, $key);
    }
}
