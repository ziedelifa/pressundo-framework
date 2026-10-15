<?php

declare(strict_types=1);

namespace Pressundo\Framework\Support;

final class Autoloader
{
    private const PREFIX = 'Pressundo\\Framework\\';

    public static function register(string $baseDir): void
    {
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($baseDir): void {
            if (! str_starts_with($class, self::PREFIX)) {
                return;
            }

            $relative = substr($class, strlen(self::PREFIX));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}
