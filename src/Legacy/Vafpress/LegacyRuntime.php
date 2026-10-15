<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class LegacyRuntime
{
    private static bool $registered = false;
    private static bool $active = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        if (defined('PRESSUNDO_VAFPRESS_COMPAT') && PRESSUNDO_VAFPRESS_COMPAT === false) {
            return;
        }

        // If a genuine Vafpress runtime was loaded before Pressundo, stay passive.
        if (defined('VP_VERSION') || class_exists('VP_Option', false) || class_exists('VP_Metabox', false)) {
            return;
        }

        self::$active = true;

        if (! defined('VP_VERSION')) {
            define('VP_VERSION', 'pressundo-compat-' . (defined('PRESSUNDO_FRAMEWORK_VERSION') ? PRESSUNDO_FRAMEWORK_VERSION : 'dev'));
        }
        if (! defined('VP_NAMESPACE')) {
            define('VP_NAMESPACE', 'VP_');
        }
        if (! defined('VP_START_TIME')) {
            define('VP_START_TIME', microtime(true));
        }
        if (! defined('VP_START_MEM')) {
            define('VP_START_MEM', memory_get_usage());
        }
        if (! defined('WPALCHEMY_MODE_ARRAY')) {
            define('WPALCHEMY_MODE_ARRAY', 'array');
        }
        if (! defined('WPALCHEMY_MODE_EXTRACT')) {
            define('WPALCHEMY_MODE_EXTRACT', 'extract');
        }

        if (! class_exists('VP_Option', false)) {
            class_alias(OptionAdapter::class, 'VP_Option');
        }
        if (! class_exists('VP_Metabox', false)) {
            class_alias(MetaboxAdapter::class, 'VP_Metabox');
        }
        if (! class_exists('VP_Security', false)) {
            class_alias(SecurityAdapter::class, 'VP_Security');
        }
        if (! class_exists('VP_Site_GoogleWebFont', false)) {
            class_alias(GoogleWebFontAdapter::class, 'VP_Site_GoogleWebFont');
        }
        if (! class_exists('VP_Option_Control_Set', false)) {
            class_alias(OptionSetAdapter::class, 'VP_Option_Control_Set');
        }
        if (! class_exists('VP_ShortcodeGenerator', false)) {
            class_alias(ShortcodeGeneratorAdapter::class, 'VP_ShortcodeGenerator');
        }

        require_once __DIR__ . '/compat-functions.php';
    }

    public static function active(): bool
    {
        return self::$active;
    }
}
