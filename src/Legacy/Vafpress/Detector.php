<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class Detector
{
    private const TEXT_EXTENSIONS = ['php', 'inc', 'json', 'txt'];
    private const NEEDLES = [
        'VP_Option' => 'option',
        'VP_Metabox' => 'metabox',
        'VP_ShortcodeGenerator' => 'shortcode_generator',
        'VP_VERSION' => 'framework_constant',
        'vp_metabox(' => 'helper',
        'vafpress' => 'reference',
    ];

    /** @return array<string,mixed> */
    public function scanPath(string $path, int $maxFiles = 2500): array
    {
        $report = [
            'path' => $path,
            'files_scanned' => 0,
            'matches' => [],
            'types' => [],
            'truncated' => false,
        ];

        if (is_file($path) && is_readable($path)) {
            $this->scanFile($path, $path, $report);
            return $report;
        }

        if (! is_dir($path) || ! is_readable($path)) {
            return $report;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                    continue;
                }
                if ($report['files_scanned'] >= $maxFiles) {
                    $report['truncated'] = true;
                    break;
                }

                $this->scanFile($file->getPathname(), $path, $report);
            }
        } catch (\UnexpectedValueException) {
            return $report;
        }

        return $report;
    }

    /** @param array<string,mixed> $report */
    private function scanFile(string $file, string $root, array &$report): void
    {
        $info = new \SplFileInfo($file);
        $ext = strtolower($info->getExtension());
        if (! in_array($ext, self::TEXT_EXTENSIONS, true) || $info->getSize() > 2_000_000) {
            return;
        }

        ++$report['files_scanned'];
        $content = @file_get_contents($file);
        if (! is_string($content) || $content === '') {
            return;
        }

        $fileMatches = [];
        foreach (self::NEEDLES as $needle => $type) {
            $count = substr_count(strtolower($content), strtolower($needle));
            if ($count > 0) {
                $fileMatches[$needle] = $count;
                $report['types'][$type] = ($report['types'][$type] ?? 0) + $count;
            }
        }

        if ($fileMatches === []) {
            return;
        }

        $normalizedFile = wp_normalize_path($file);
        $normalizedRoot = wp_normalize_path($root);
        $relative = is_file($root)
            ? basename($normalizedFile)
            : ltrim(str_replace($normalizedRoot, '', $normalizedFile), '/');

        $report['matches'][] = [
            'file' => $normalizedFile,
            'relative' => $relative,
            'matches' => $fileMatches,
        ];
    }
}
