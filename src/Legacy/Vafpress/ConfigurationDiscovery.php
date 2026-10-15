<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class ConfigurationDiscovery
{
    public function __construct(private readonly TemplateConverter $converter) {}

    /** @return array{configurations:list<array<string,mixed>>,warnings:list<string>} */
    public function discover(string $targetRoot, array $matchedFiles, array $contextPaths = []): array
    {
        $configurations = [];
        $warnings = [];

        foreach ($matchedFiles as $match) {
            $file = (string) ($match['file'] ?? '');
            if ($file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            $source = @file_get_contents($file);
            if (! is_string($source) || $source === '') {
                continue;
            }

            foreach ($this->findConstructors($source) as $constructor) {
                $class = $constructor['class'];
                if (! in_array($class, ['VP_Option', 'VP_Metabox'], true)) {
                    continue;
                }

                $templateRoot = isset($contextPaths['template_root']) && is_string($contextPaths['template_root']) ? $contextPaths['template_root'] : $targetRoot;
                $stylesheetRoot = isset($contextPaths['stylesheet_root']) && is_string($contextPaths['stylesheet_root']) ? $contextPaths['stylesheet_root'] : $targetRoot;
                $parser = new SafePhpExpressionParser($file, $targetRoot, $templateRoot, $stylesheetRoot);
                $parsed = $parser->parse($constructor['argument']);
                $configWarnings = (array) $parsed['warnings'];
                foreach ($configWarnings as $warning) {
                    $warnings[] = basename($file) . ': ' . $warning;
                }

                if (! is_array($parsed['value'])) {
                    $warnings[] = basename($file) . ': unable to parse ' . $class . ' configuration array safely.';
                    continue;
                }

                $config = $parsed['value'];
                $template = $config['template'] ?? null;
                $templateFile = null;
                $templateWarnings = [];

                if (is_string($template) && $template !== '') {
                    $templateFile = $this->resolveTemplatePath($template, $file, $targetRoot, [$templateRoot, $stylesheetRoot]);
                    if ($templateFile !== null) {
                        $templateParsed = (new SafePhpExpressionParser($templateFile, $targetRoot, $templateRoot, $stylesheetRoot))->parseReturnedArrayFile($templateFile);
                        $template = $templateParsed['value'];
                        $templateWarnings = $templateParsed['warnings'];
                    } else {
                        $templateWarnings[] = 'Template path could not be resolved inside the scanned target.';
                    }
                }

                if (! is_array($template)) {
                    $warnings = array_merge($warnings, array_map(static fn (string $w): string => basename($file) . ': ' . $w, $templateWarnings));
                    $allWarnings = array_values(array_unique(array_merge($configWarnings, $templateWarnings)));
                    $configurations[] = $this->unresolvedConfiguration($class, $config, $file, $templateFile, $allWarnings);
                    continue;
                }

                $schemaId = $class === 'VP_Option'
                    ? (string) ($config['option_key'] ?? $config['page_slug'] ?? 'vafpress-options')
                    : (string) ($config['id'] ?? 'vafpress-metabox');

                $converted = $this->converter->convert($template, $schemaId);
                $schema = $converted['schema'];
                $schema['legacy_config'] = $this->extractLegacyConfig($class, $config);

                $configuration = [
                    'class' => $class,
                    'kind' => $class === 'VP_Option' ? 'options' : 'metabox',
                    'source_file' => $file,
                    'source_relative' => $this->relativePath($file, $targetRoot),
                    'template_file' => $templateFile,
                    'template_relative' => $templateFile !== null ? $this->relativePath($templateFile, $targetRoot) : null,
                    'config' => $this->extractLegacyConfig($class, $config),
                    'schema' => $schema,
                    'report' => $converted['report'],
                    'warnings' => array_values(array_unique(array_merge($configWarnings, $templateWarnings))),
                    'resolved' => true,
                ];
                $configuration['compatibility'] = $this->compatibilityScore((array) $converted['report']);
                $configurations[] = $configuration;
            }
        }

        return [
            'configurations' => $configurations,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @return list<array{class:string,argument:string}> */
    private function findConstructors(string $source): array
    {
        $tokens = token_get_all($source);
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (! is_array($token) || $token[0] !== T_NEW) {
                continue;
            }

            $j = $this->nextMeaningful($tokens, $i + 1);
            if ($j === null || ! is_array($tokens[$j])) {
                continue;
            }
            $class = ltrim((string) $tokens[$j][1], '\\');
            if (! in_array($class, ['VP_Option', 'VP_Metabox'], true)) {
                continue;
            }

            $j = $this->nextMeaningful($tokens, $j + 1);
            if ($j === null || $tokens[$j] !== '(') {
                continue;
            }

            [$argument, $end] = $this->captureFirstArgument($tokens, $j + 1);
            if ($argument !== '') {
                $found[] = ['class' => $class, 'argument' => $argument];
            }
            $i = max($i, $end);
        }

        return $found;
    }

    /** @param array<int,array{0:int,1:string,2?:int}|string> $tokens @return array{0:string,1:int} */
    private function captureFirstArgument(array $tokens, int $start): array
    {
        $argument = '';
        $paren = 0;
        $bracket = 0;
        $brace = 0;
        $count = count($tokens);

        for ($i = $start; $i < $count; ++$i) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(') {
                ++$paren;
            } elseif ($text === ')') {
                if ($paren === 0 && $bracket === 0 && $brace === 0) {
                    return [trim($argument), $i];
                }
                $paren = max(0, $paren - 1);
            } elseif ($text === '[') {
                ++$bracket;
            } elseif ($text === ']') {
                $bracket = max(0, $bracket - 1);
            } elseif ($text === '{') {
                ++$brace;
            } elseif ($text === '}') {
                $brace = max(0, $brace - 1);
            } elseif ($text === ',' && $paren === 0 && $bracket === 0 && $brace === 0) {
                return [trim($argument), $i];
            }

            $argument .= $text;
        }

        return [trim($argument), $count - 1];
    }

    /** @param array<int,array{0:int,1:string,2?:int}|string> $tokens */
    private function nextMeaningful(array $tokens, int $start): ?int
    {
        for ($i = $start, $count = count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $i;
        }
        return null;
    }

    private function resolveTemplatePath(string $template, string $sourceFile, string $targetRoot, array $additionalRoots = []): ?string
    {
        $candidates = [];
        if ($this->isAbsolutePath($template)) {
            $candidates[] = $template;
        } else {
            $candidates[] = dirname($sourceFile) . '/' . ltrim($template, '/\\');
            $candidates[] = rtrim($targetRoot, '/\\') . '/' . ltrim($template, '/\\');
        }

        $allowedRoots = [$targetRoot];
        foreach ($additionalRoots as $root) {
            if (is_string($root) && $root !== '') {
                $allowedRoots[] = $root;
            }
        }
        $allowedRoots = array_values(array_unique(array_map(static fn (string $root): string => rtrim(wp_normalize_path($root), '/'), $allowedRoots)));

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false || ! is_file($real) || ! is_readable($real)) {
                continue;
            }
            $normalized = wp_normalize_path($real);
            $allowed = false;
            foreach ($allowedRoots as $normalizedRoot) {
                if ($normalizedRoot !== '' && ($normalized === $normalizedRoot || str_starts_with($normalized, $normalizedRoot . '/'))) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                continue;
            }
            return $normalized;
        }
        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    private function extractLegacyConfig(string $class, array $config): array
    {
        $keys = $class === 'VP_Option'
            ? ['option_key', 'page_slug', 'menu_page', 'minimum_role', 'layout', 'page_title', 'menu_label', 'priority', 'is_dev_mode']
            : ['id', 'types', 'title', 'priority', 'context', 'mode', 'prefix', 'hide_editor', 'is_dev_mode'];

        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                $result[$key] = $config[$key];
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $config @param list<string> $templateWarnings @return array<string,mixed> */
    private function unresolvedConfiguration(string $class, array $config, string $file, ?string $templateFile, array $templateWarnings): array
    {
        return [
            'class' => $class,
            'kind' => $class === 'VP_Option' ? 'options' : 'metabox',
            'source_file' => $file,
            'source_relative' => basename($file),
            'template_file' => $templateFile,
            'template_relative' => $templateFile !== null ? basename($templateFile) : null,
            'config' => $this->extractLegacyConfig($class, $config),
            'schema' => null,
            'report' => ['automatic' => 0, 'review' => 0, 'manual' => 1, 'fields' => [], 'warnings' => $templateWarnings],
            'warnings' => $templateWarnings,
            'resolved' => false,
            'compatibility' => 0,
        ];
    }

    /** @param array<string,mixed> $report */
    private function compatibilityScore(array $report): int
    {
        $automatic = (int) ($report['automatic'] ?? 0);
        $review = (int) ($report['review'] ?? 0);
        $manual = (int) ($report['manual'] ?? 0);
        $total = $automatic + $review + $manual;
        if ($total === 0) {
            return 100;
        }
        return (int) round((($automatic + ($review * 0.5)) / $total) * 100);
    }

    private function relativePath(string $file, string $root): string
    {
        $file = wp_normalize_path($file);
        $root = rtrim(wp_normalize_path($root), '/');
        return ltrim(str_replace($root, '', $file), '/');
    }
}
