<?php

declare(strict_types=1);

namespace Pressundo\Framework\Security;

/**
 * Central security policy for untrusted request data and legacy compatibility.
 *
 * Legacy PHP execution is disabled by default. Explicit opt-ins live behind
 * wp-config.php constants so a stored option, request parameter, or theme field
 * can never silently enable code execution.
 */
final class SecurityPolicy
{
    public static function allowLegacyWhitelistedCallbacks(): bool
    {
        return defined('PRESSUNDO_VAFPRESS_ALLOW_WHITELISTED_CALLBACKS')
            && PRESSUNDO_VAFPRESS_ALLOW_WHITELISTED_CALLBACKS === true;
    }

    public static function allowUnsafeLegacyTemplateInclude(): bool
    {
        return defined('PRESSUNDO_VAFPRESS_ALLOW_UNSAFE_TEMPLATE_INCLUDE')
            && PRESSUNDO_VAFPRESS_ALLOW_UNSAFE_TEMPLATE_INCLUDE === true;
    }

    public static function httpUrl(mixed $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        if (function_exists('esc_url_raw')) {
            return (string) esc_url_raw($value, ['http', 'https']);
        }

        $parts = wp_parse_url($value);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        return in_array($scheme, ['http', 'https'], true) ? $value : '';
    }

    /**
     * Safe fallback sanitizer for values whose field type is not registered.
     * Unknown controls must never become a raw-data bypass.
     */
    public static function sanitizeUnknown(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return null;
        }
        if (is_array($value)) {
            $out = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if (++$count > self::maxInputItems()) {
                    break;
                }
                $safeKey = is_int($key) ? $key : self::text($key);
                $out[$safeKey] = self::sanitizeUnknown($item, $depth + 1);
            }
            return $out;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return self::text((string) $value);
        }
        return null;
    }

    public static function text(mixed $value): string
    {
        $value = (string) $value;

        // Keep fallback sanitization deterministic in and outside WordPress.
        // WordPress' sanitize_text_field() removes script/style blocks (including
        // their contents) through wp_strip_all_tags(); mirror that behaviour
        // before calling a host-provided sanitizer so test/runtime semantics match.
        $value = (string) preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', '', $value);

        return sanitize_text_field($value);
    }

    public static function revision(mixed $value): string
    {
        return hash('sha256', serialize($value));
    }

    public static function revisionsMatch(mixed $submitted, mixed $current): bool
    {
        $submitted = strtolower(trim((string) $submitted));
        return preg_match('/^[a-f0-9]{64}$/', $submitted) === 1
            && hash_equals(self::revision($current), $submitted);
    }

    public static function maxRepeaterRows(): int
    {
        $limit = 250;
        if (function_exists('apply_filters')) {
            $limit = (int) apply_filters('pressundo_security_max_repeater_rows', $limit);
        }
        return max(1, min(5000, $limit));
    }

    public static function maxInputItems(): int
    {
        $limit = 2000;
        if (function_exists('apply_filters')) {
            $limit = (int) apply_filters('pressundo_security_max_input_items', $limit);
        }
        return max(100, min(10000, $limit));
    }
}
