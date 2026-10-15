<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class LegacyDataSources
{
    /** @var array<string,mixed>|null */
    private static ?array $googleFonts = null;
    /** @var list<string>|null */
    private static ?array $fontAwesome = null;

    /** @return array<string,mixed> */
    public static function googleFonts(): array
    {
        if (self::$googleFonts !== null) {
            return self::$googleFonts;
        }
        $file = self::resourcePath('gwf.json');
        $decoded = is_readable($file) ? json_decode((string) file_get_contents($file), true) : [];
        self::$googleFonts = is_array($decoded) ? $decoded : [];
        return self::$googleFonts;
    }

    /** @return list<string> */
    public static function fontAwesomeIcons(): array
    {
        if (self::$fontAwesome !== null) {
            return self::$fontAwesome;
        }
        $file = self::resourcePath('fontawesome-icons.json');
        $decoded = is_readable($file) ? json_decode((string) file_get_contents($file), true) : [];
        self::$fontAwesome = array_values(array_filter(array_map('strval', is_array($decoded) ? $decoded : [])));
        return self::$fontAwesome;
    }

    private static function resourcePath(string $file): string
    {
        $root = defined('PRESSUNDO_FRAMEWORK_DIR') ? PRESSUNDO_FRAMEWORK_DIR : dirname(__DIR__, 4) . '/';
        return rtrim((string) $root, '/\\') . '/resources/legacy/vafpress/' . $file;
    }
}
