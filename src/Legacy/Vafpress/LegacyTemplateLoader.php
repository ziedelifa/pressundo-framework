<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use RuntimeException;
use Pressundo\Framework\Security\SecurityPolicy;

final class LegacyTemplateLoader
{
    /** @return array<mixed> */
    public function load(mixed $template): array
    {
        if (is_array($template)) {
            return $this->filter($template, null);
        }

        if (! is_string($template) || $template === '' || ! is_readable($template)) {
            throw new RuntimeException('Vafpress compatibility template could not be resolved.');
        }

        $real = realpath($template);
        if ($real === false || ! is_file($real)) {
            throw new RuntimeException('Vafpress compatibility template could not be resolved.');
        }

        $root = function_exists('get_template_directory') ? (string) get_template_directory() : dirname($real);
        $stylesheetRoot = function_exists('get_stylesheet_directory') ? (string) get_stylesheet_directory() : $root;
        $templateUri = function_exists('get_template_directory_uri') ? (string) get_template_directory_uri() : '';
        $stylesheetUri = function_exists('get_stylesheet_directory_uri') ? (string) get_stylesheet_directory_uri() : $templateUri;
        $parsed = (new SafePhpExpressionParser($real, $root, $root, $stylesheetRoot, $templateUri, $stylesheetUri))->parseReturnedArrayFile($real);

        if (is_array($parsed['value'])) {
            return $this->filter($parsed['value'], $real);
        }

        $allowInclude = SecurityPolicy::allowUnsafeLegacyTemplateInclude();
        if ($allowInclude && function_exists('apply_filters')) {
            $allowInclude = (bool) apply_filters('pressundo_vafpress_allow_template_include', true, $real, (array) ($parsed['warnings'] ?? []));
        }

        if (! $allowInclude) {
            throw new RuntimeException('Vafpress template uses PHP expressions that the safe compatibility parser cannot resolve. Unsafe PHP include is disabled by default.');
        }

        $value = (static function (string $__file): mixed {
            return include $__file;
        })($real);

        if (! is_array($value)) {
            throw new RuntimeException('Vafpress compatibility template did not return an array.');
        }

        return $this->filter($value, $real);
    }

    /** @param array<mixed> $template @return array<mixed> */
    private function filter(array $template, ?string $file): array
    {
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('pressundo_vafpress_template_array', $template, $file);
            if (is_array($filtered)) {
                return $filtered;
            }
        }

        return $template;
    }
}
