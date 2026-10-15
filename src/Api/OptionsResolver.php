<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

use Throwable;

final class OptionsResolver
{
    /** @var array<string,array<string,mixed>> */
    private array $cache = [];

    /** @param array<string,mixed> $field @return array<string,mixed> */
    public function resolve(array $field): array
    {
        $source = $field['options_callback'] ?? $field['options'] ?? [];
        $cacheKey = $this->cacheKey($field, $source);
        if ($cacheKey !== '' && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $options = [];
        try {
            if (is_callable($source)) {
                $resolved = $source($field);
                $options = is_array($resolved) ? $resolved : [];
            } elseif (is_array($source)) {
                $options = $source;
            }
        } catch (Throwable $e) {
            if (function_exists('do_action')) {
                do_action('pressundo_dynamic_options_error', $e, $field);
            }
            $options = [];
        }

        $options = $this->normalize($options);
        if (function_exists('apply_filters')) {
            /** @var array<string,mixed> $options */
            $options = (array) apply_filters('pressundo_field_options', $options, $field);
            $type = sanitize_key((string) ($field['type'] ?? ''));
            if ($type !== '') {
                /** @var array<string,mixed> $options */
                $options = (array) apply_filters('pressundo_field_options_' . $type, $options, $field);
            }
            $options = $this->normalize($options);
        }

        if ($cacheKey !== '') {
            $this->cache[$cacheKey] = $options;
        }
        return $options;
    }

    /** @param array<mixed> $options @return array<string,mixed> */
    private function normalize(array $options): array
    {
        $normalized = [];
        foreach ($options as $key => $option) {
            if (is_array($option) && array_key_exists('value', $option)) {
                $value = (string) $option['value'];
                $normalized[$value] = [
                    'label' => (string) ($option['label'] ?? $value),
                ] + $option;
                continue;
            }

            if (is_int($key)) {
                if (is_scalar($option)) {
                    $value = (string) $option;
                    $normalized[$value] = $value;
                }
                continue;
            }

            $normalized[(string) $key] = $option;
        }
        return $normalized;
    }

    /** @param array<string,mixed> $field */
    private function cacheKey(array $field, mixed $source): string
    {
        if (! empty($field['options_no_cache'])) {
            return '';
        }
        if ($source instanceof \Closure) {
            return 'closure:' . spl_object_id($source);
        }
        if (is_array($source) && is_callable($source)) {
            $owner = is_object($source[0] ?? null) ? get_class($source[0]) . '#' . spl_object_id($source[0]) : (string) ($source[0] ?? '');
            return 'callable:' . md5($owner . '::' . (string) ($source[1] ?? ''));
        }
        if (is_string($source) && is_callable($source)) {
            return 'callable:' . $source;
        }
        if (is_array($source)) {
            return 'array:' . md5(serialize($source));
        }
        $id = (string) ($field['id'] ?? $field['name'] ?? '');
        return $id !== '' ? 'field:' . $id : '';
    }
}
