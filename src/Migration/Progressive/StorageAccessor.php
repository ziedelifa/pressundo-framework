<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration\Progressive;

final class StorageAccessor
{
    public function exists(StorageLocation $location): bool
    {
        if ($location->type === 'option') {
            $marker = new \stdClass();
            $container = get_option($location->key, $marker);
            if ($container === $marker) {
                return false;
            }

            return $location->path === [] || Path::has($container, $location->path);
        }

        if (! metadata_exists('post', $location->postId, $location->key)) {
            return false;
        }

        if ($location->path === []) {
            return true;
        }

        $container = get_post_meta($location->postId, $location->key, true);
        return Path::has($container, $location->path);
    }

    public function get(StorageLocation $location, mixed $default = null): mixed
    {
        if (! $this->exists($location)) {
            return $default;
        }

        $container = $location->type === 'option'
            ? get_option($location->key)
            : get_post_meta($location->postId, $location->key, true);

        return $location->path === [] ? $container : Path::get($container, $location->path, $default);
    }

    public function set(StorageLocation $location, mixed $value): bool
    {
        if ($this->exists($location) && $this->get($location) === $value) {
            return true;
        }

        if ($location->path === []) {
            return $location->type === 'option'
                ? update_option($location->key, $value, false)
                : false !== update_post_meta($location->postId, $location->key, $value);
        }

        $container = $this->readContainer($location);
        $container = Path::set($container, $location->path, $value);

        return $location->type === 'option'
            ? update_option($location->key, $container, false)
            : false !== update_post_meta($location->postId, $location->key, $container);
    }

    public function delete(StorageLocation $location): bool
    {
        if (! $this->exists($location)) {
            return true;
        }

        if ($location->path === []) {
            return $location->type === 'option'
                ? delete_option($location->key)
                : delete_post_meta($location->postId, $location->key);
        }

        $container = $this->readContainer($location);
        $container = Path::delete($container, $location->path);

        return $location->type === 'option'
            ? update_option($location->key, $container, false)
            : false !== update_post_meta($location->postId, $location->key, $container);
    }

    private function readContainer(StorageLocation $location): mixed
    {
        if ($location->type === 'option') {
            return get_option($location->key, []);
        }

        if (! metadata_exists('post', $location->postId, $location->key)) {
            return [];
        }

        return get_post_meta($location->postId, $location->key, true);
    }
}
