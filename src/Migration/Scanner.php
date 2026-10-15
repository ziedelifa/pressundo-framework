<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

use Pressundo\Framework\Legacy\Vafpress\ConfigurationDiscovery;
use Pressundo\Framework\Legacy\Vafpress\Detector;

final class Scanner
{
    public function __construct(
        private readonly Detector $detector,
        private readonly ConfigurationDiscovery $discovery,
        private readonly MigrationPlanBuilder $planBuilder,
        private readonly ShadowDataAnalyzer $shadowAnalyzer,
        private readonly ShadowSaveComparator $shadowSaveComparator,
        private readonly MigrationReadinessEvaluator $readinessEvaluator,
        private readonly RuntimeCutoverPlanner $cutoverPlanner,
        private readonly RuntimeTakeoverPreview $takeoverPreview
    ) {}

    /** @return array<string,mixed> */
    public function scan(): array
    {
        $targets = [];
        $theme = wp_get_theme();
        $stylesheetDir = get_stylesheet_directory();
        $templateDir = get_template_directory();

        $targets[] = [
            'kind' => 'theme',
            'name' => $theme->get('Name') ?: basename($stylesheetDir),
            'path' => $stylesheetDir,
        ];

        if (wp_normalize_path($templateDir) !== wp_normalize_path($stylesheetDir)) {
            $parent = $theme->parent();
            $targets[] = [
                'kind' => 'parent_theme',
                'name' => $parent ? ($parent->get('Name') ?: basename($templateDir)) : basename($templateDir),
                'path' => $templateDir,
            ];
        }

        $plugins = get_option('active_plugins', []);
        $selfPlugin = defined('PRESSUNDO_FRAMEWORK_FILE')
            ? plugin_basename((string) PRESSUNDO_FRAMEWORK_FILE)
            : '';

        foreach ((array) $plugins as $pluginFile) {
            $pluginFile = plugin_basename((string) $pluginFile);

            // Never analyse the framework itself as a legacy Vafpress target.
            // Its compatibility classes intentionally contain Vafpress symbols,
            // which would otherwise create a false-positive candidate.
            if ($selfPlugin !== '' && $pluginFile === $selfPlugin) {
                continue;
            }

            $absolutePluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
            $dir = dirname($absolutePluginFile);
            $path = $dir === WP_PLUGIN_DIR ? $absolutePluginFile : $dir;
            $targets[] = [
                'kind' => 'plugin',
                'name' => $pluginFile,
                'path' => $path,
            ];
        }

        $results = [];
        $totals = [
            'files_scanned' => 0,
            'matched_files' => 0,
            'targets_with_vafpress' => 0,
            'configurations' => 0,
            'resolved_configurations' => 0,
            'fields' => 0,
            'automatic' => 0,
            'review' => 0,
            'manual' => 0,
            'ready' => 0,
            'needs_review' => 0,
            'blocked' => 0,
        ];

        foreach ($targets as $target) {
            $scan = $this->detector->scanPath((string) $target['path']);
            $hasVafpress = ! empty($scan['matches']);
            $discovery = ['configurations' => [], 'warnings' => []];

            if ($hasVafpress) {
                ++$totals['targets_with_vafpress'];
                $discovery = $this->discovery->discover(
                    (string) $target['path'],
                    (array) $scan['matches'],
                    ['template_root' => $templateDir, 'stylesheet_root' => $stylesheetDir]
                );
            }

            $totals['files_scanned'] += (int) $scan['files_scanned'];
            $totals['matched_files'] += count((array) $scan['matches']);

            foreach ((array) $discovery['configurations'] as $index => $configuration) {
                if (! is_array($configuration)) {
                    continue;
                }
                $configuration['plan'] = $this->planBuilder->build($configuration);
                $configuration['shadow_data'] = $this->shadowAnalyzer->analyze($configuration);
                $configuration['shadow_save'] = $this->shadowSaveComparator->compare($configuration, (array) $configuration['shadow_data']);
                $configuration['plan']['shadow_save_gate_passed'] = ! empty($configuration['shadow_save']['write_gate_passed'])
                    && (($configuration['shadow_data']['status'] ?? '') === 'ready');
                $storageType = (string) ($configuration['plan']['storage']['type'] ?? '');
                $configuration['plan']['ready_for_write_migration'] = ! empty($configuration['plan']['shadow_save_gate_passed'])
                    && in_array($storageType, ['wp_option', 'post_meta_array'], true);
                $configuration['readiness'] = $this->readinessEvaluator->evaluate($configuration);
                $configuration['cutover'] = $this->cutoverPlanner->build($configuration);
                $configuration['takeover_preview'] = $this->takeoverPreview->build($configuration);
                $readinessStatus = (string) ($configuration['readiness']['status'] ?? 'blocked');
                if (array_key_exists($readinessStatus, $totals)) {
                    ++$totals[$readinessStatus];
                }
                $discovery['configurations'][$index] = $configuration;
                ++$totals['configurations'];
                if (! empty($configuration['resolved'])) {
                    ++$totals['resolved_configurations'];
                }
                $report = (array) ($configuration['report'] ?? []);
                $automatic = (int) ($report['automatic'] ?? 0);
                $review = (int) ($report['review'] ?? 0);
                $manual = (int) ($report['manual'] ?? 0);
                $totals['automatic'] += $automatic;
                $totals['review'] += $review;
                $totals['manual'] += $manual;
                $totals['fields'] += $automatic + $review + $manual;
            }

            $results[] = $target + [
                'scan' => $scan,
                'discovery' => $discovery,
                'has_vafpress' => $hasVafpress,
            ];
        }

        $weightedCompatible = $totals['automatic'] + ($totals['review'] * 0.5);
        $totals['compatibility'] = $totals['fields'] > 0
            ? (int) round(($weightedCompatible / $totals['fields']) * 100)
            : ($totals['configurations'] > 0 ? 0 : 100);

        return [
            'generated_at' => current_time('mysql'),
            'mode' => 'migration_readiness',
            'totals' => $totals,
            'targets' => $results,
        ];
    }
}
