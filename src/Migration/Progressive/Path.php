<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration\Progressive;

final class Path
{
    /** @return list<string|int> */
    public static function normalize(string|int|array|null $path): array
    {
        if ($path === null || $path === '' || $path === []) {
            return [];
        }

        if (is_int($path)) {
            return [$path];
        }

        if (is_array($path)) {
            return array_values(array_map(static function (mixed $part): string|int {
                if (is_int($part)) {
                    return $part;
                }

                $part = (string) $part;
                return ctype_digit($part) ? (int) $part : $part;
            }, $path));
        }

        $parts = array_values(array_filter(explode('.', $path), static fn (string $part): bool => $part !== ''));
        return array_map(static fn (string $part): string|int => ctype_digit($part) ? (int) $part : $part, $parts);
    }

    /** @param list<string|int> $path */
    public static function has(mixed $value, array $path): bool
    {
        if ($path === []) {
            return true;
        }

        $cursor = $value;
        foreach ($path as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                return false;
            }
            $cursor = $cursor[$part];
        }

        return true;
    }

    /** @param list<string|int> $path */
    public static function get(mixed $value, array $path, mixed $default = null): mixed
    {
        if ($path === []) {
            return $value;
        }

        $cursor = $value;
        foreach ($path as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }

        return $cursor;
    }

    /** @param list<string|int> $path */
    public static function set(mixed $value, array $path, mixed $newValue): mixed
    {
        if ($path === []) {
            return $newValue;
        }

        if (! is_array($value)) {
            $value = [];
        }

        $cursor =& $value;
        $last = array_pop($path);
        foreach ($path as $part) {
            if (! isset($cursor[$part]) || ! is_array($cursor[$part])) {
                $cursor[$part] = [];
            }
            $cursor =& $cursor[$part];
        }
        $cursor[$last] = $newValue;

        return $value;
    }

    /** @param list<string|int> $path */
    public static function delete(mixed $value, array $path): mixed
    {
        if ($path === []) {
            return null;
        }

        if (! is_array($value)) {
            return $value;
        }

        $cursor =& $value;
        $last = array_pop($path);
        foreach ($path as $part) {
            if (! isset($cursor[$part]) || ! is_array($cursor[$part])) {
                return $value;
            }
            $cursor =& $cursor[$part];
        }

        unset($cursor[$last]);
        return $value;
    }
}
