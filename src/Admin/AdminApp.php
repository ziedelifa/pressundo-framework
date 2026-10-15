<?php

declare(strict_types=1);

namespace Pressundo\Framework\Admin;

use Pressundo\Framework\Migration\Scanner;
use Pressundo\Framework\Migration\ShadowSubmissionSimulator;
use Pressundo\Framework\Migration\ControlledWriteService;
use Pressundo\Framework\Migration\SnapshotRepository;
use Pressundo\Framework\Legacy\Vafpress\MigrationOverrideRepository;
use Pressundo\Framework\Renderer\SchemaFormRenderer;
use Pressundo\Framework\Renderer\SchemaPreviewRenderer;

final class AdminApp
{
    private const NONCE_ACTION = 'pressundo_framework_scan';

    /** @var array<string,array<string,mixed>> */
    private array $shadowSubmissionResults = [];

    /** @var array<string,array<string,mixed>> */
    private array $controlledWriteResults = [];

    /** @var array<string,mixed>|null */
    private ?array $rollbackResult = null;

    private string $overrideNotice = '';

    public function __construct(
        private readonly Scanner $scanner,
        private readonly SchemaPreviewRenderer $previewRenderer,
        private readonly SchemaFormRenderer $formRenderer,
        private readonly ShadowSubmissionSimulator $shadowSubmissionSimulator,
        private readonly ControlledWriteService $controlledWriteService,
        private readonly SnapshotRepository $snapshotRepository,
        private readonly MigrationOverrideRepository $overrideRepository
    ) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu(): void
    {
        add_management_page(
            __('Pressundo Framework', 'pressundo-framework'),
            __('Pressundo Framework', 'pressundo-framework'),
            'manage_options',
            'pressundo-framework',
            [$this, 'render']
        );
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'tools_page_pressundo-framework') {
            return;
        }
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        wp_enqueue_style('pressundo-framework-admin', PRESSUNDO_FRAMEWORK_URL . 'assets/css/admin.css', [], PRESSUNDO_FRAMEWORK_VERSION);
        wp_enqueue_script('pressundo-framework-admin', PRESSUNDO_FRAMEWORK_URL . 'assets/js/admin.js', [], PRESSUNDO_FRAMEWORK_VERSION, true);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'pressundo-framework'));
        }

        $diagnosticsEnabled = $this->diagnosticsEnabled();
        $report = null;
        if ($diagnosticsEnabled) {
            if (isset($_POST['pressundo_save_override']) || isset($_POST['pressundo_remove_override'])) {
                $this->handleMigrationOverride();
                $report = $this->scanner->scan();
            } elseif (isset($_POST['pressundo_scan'])) {
                check_admin_referer(self::NONCE_ACTION);
                $report = $this->scanner->scan();
            } elseif (isset($_POST['pressundo_shadow_submit'])) {
                $report = $this->scanner->scan();
                $this->handleShadowSubmission($report);
            } elseif (isset($_POST['pressundo_controlled_write'])) {
                $report = $this->scanner->scan();
                $this->handleControlledWrite($report);
                $report = $this->scanner->scan();
            } elseif (isset($_POST['pressundo_rollback'])) {
                $this->handleRollback();
                $report = $this->scanner->scan();
            }
        }

        echo '<div class="wrap pressundo-framework">';
        echo '<div class="pressundo-hero">';
        echo '<div><p class="pressundo-eyebrow">' . esc_html__('Pressundo Framework 0.13.0-dev', 'pressundo-framework') . '</p>';
        echo '<h1>' . esc_html__('Modern WordPress framework + progressive legacy migration', 'pressundo-framework') . '</h1>';
        echo '<p>' . esc_html__('Modernize theme and plugin code progressively. Existing clients can keep legacy data while new installations use the modern API naturally.', 'pressundo-framework') . '</p></div>';
        echo '<span class="pressundo-mode">' . esc_html__('Free 0.13', 'pressundo-framework') . '</span>';
        echo '</div>';

        echo '<div class="pressundo-grid">';
        $this->card(__('Modern API', 'pressundo-framework'), __('Simple pu_get/pu_set plus option and post-meta namespaces for new WordPress development.', 'pressundo-framework'), __('Ready', 'pressundo-framework'));
        $this->card(__('Progressive Migration', 'pressundo-framework'), __('Preserve, lazy and copy strategies let old client data keep working while source code moves forward.', 'pressundo-framework'), __('0.10', 'pressundo-framework'));
        echo '</div>';

        echo '<div class="pressundo-panel pressundo-api-panel">';
        echo '<div class="pressundo-panel__head"><div><h2>' . esc_html__('Progressive Migration API', 'pressundo-framework') . '</h2>';
        echo '<p>' . esc_html__('No migration wizard is required for normal theme development. Add migrate_from only where old client data must remain compatible.', 'pressundo-framework') . '</p></div></div>';
        echo '<div class="pressundo-api-examples">';
        echo '<div><strong>' . esc_html__('New project', 'pressundo-framework') . '</strong><pre><code>' . esc_html("pu_set('theme_color', '#3858e9');\n\$color = pu_get('theme_color', '#000000');") . '</code></pre></div>';
        echo '<div><strong>' . esc_html__('Existing project', 'pressundo-framework') . '</strong><pre><code>' . esc_html("\$theme = pu_options('theme', [\n    'storage_key'  => 'pressundo_theme',\n    'migrate_from' => 'old_theme_options',\n]);") . '</code></pre></div>';
        echo '</div>';
        echo '<p class="pressundo-plan__note">' . esc_html__('Default strategy: preserve. Existing legacy storage stays in place; a new installation uses the modern key.', 'pressundo-framework') . '</p>';
        echo '</div>';

        if ($diagnosticsEnabled) {
            echo '<div class="pressundo-panel">';
            echo '<div class="pressundo-panel__head"><div><h2>' . esc_html__('Advanced Vafpress Diagnostics', 'pressundo-framework') . '</h2>';
            echo '<p>' . esc_html__('Optional staging diagnostics. Analysis and shadow simulation are read-only; database writes occur only after explicit confirmation.', 'pressundo-framework') . '</p></div>';
            echo '<form method="post">';
            wp_nonce_field(self::NONCE_ACTION);
            submit_button(__('Analyze current site', 'pressundo-framework'), 'primary', 'pressundo_scan', false);
            echo '</form></div>';

            if ($this->overrideNotice !== '') {
                echo '<div class="notice notice-success inline"><p>' . esc_html($this->overrideNotice) . '</p></div>';
            }
            $this->renderOverrideRegistry();
            if (is_array($this->rollbackResult)) {
                $this->renderRollbackResult($this->rollbackResult);
            }
            if (is_array($report)) {
                $this->renderReport($report);
            }
            $this->renderSnapshotHistory();
            echo '</div></div>';
        } else {
            echo '</div>';
        }
    }

    private function diagnosticsEnabled(): bool
    {
        return defined('PRESSUNDO_FRAMEWORK_DEBUG') && PRESSUNDO_FRAMEWORK_DEBUG === true;
    }

    private function card(string $title, string $body, string $badge): void
    {
        echo '<div class="pressundo-card"><span class="pressundo-badge">' . esc_html($badge) . '</span><h2>' . esc_html($title) . '</h2><p>' . esc_html($body) . '</p></div>';
    }

    /** @param array<string,mixed> $report */
    private function renderReport(array $report): void
    {
        $totals = (array) ($report['totals'] ?? []);
        echo '<div class="pressundo-report">';
        echo '<div class="pressundo-report__title"><div><p class="pressundo-eyebrow">' . esc_html__('Migration dry run', 'pressundo-framework') . '</p><h3>' . esc_html__('Analysis results', 'pressundo-framework') . '</h3></div>';
        echo '<div class="pressundo-score"><strong>' . esc_html((string) ($totals['compatibility'] ?? 0)) . '%</strong><span>' . esc_html__('compatibility', 'pressundo-framework') . '</span></div></div>';

        echo '<div class="pressundo-stats pressundo-stats--five">';
        $this->stat((string) ($totals['configurations'] ?? 0), __('Configurations', 'pressundo-framework'));
        $this->stat((string) ($totals['fields'] ?? 0), __('Fields', 'pressundo-framework'));
        $this->stat((string) ($totals['automatic'] ?? 0), __('Automatic', 'pressundo-framework'));
        $this->stat((string) ($totals['review'] ?? 0), __('Review', 'pressundo-framework'));
        $this->stat((string) ($totals['manual'] ?? 0), __('Manual', 'pressundo-framework'));
        echo '</div>';

        echo '<div class="pressundo-stats">';
        $this->stat((string) ($totals['ready'] ?? 0), __('Ready', 'pressundo-framework'));
        $this->stat((string) ($totals['needs_review'] ?? 0), __('Needs review', 'pressundo-framework'));
        $this->stat((string) ($totals['blocked'] ?? 0), __('Blocked', 'pressundo-framework'));
        echo '</div>';

        if ((int) ($totals['targets_with_vafpress'] ?? 0) === 0) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('No Vafpress usage was detected in the active theme or active plugins.', 'pressundo-framework') . '</p></div>';
            echo '</div>';
            return;
        }

        foreach ((array) ($report['targets'] ?? []) as $target) {
            if (empty($target['has_vafpress'])) {
                continue;
            }
            $this->renderTarget((array) $target);
        }
        echo '</div>';
    }

    /** @param array<string,mixed> $target */
    private function renderTarget(array $target): void
    {
        $discovery = (array) ($target['discovery'] ?? []);
        $configurations = (array) ($discovery['configurations'] ?? []);
        $scan = (array) ($target['scan'] ?? []);

        echo '<section class="pressundo-target">';
        echo '<div class="pressundo-target__head"><div><span class="pressundo-kicker">' . esc_html((string) ($target['kind'] ?? 'target')) . '</span><h3>' . esc_html((string) ($target['name'] ?? '')) . '</h3></div>';
        /* translators: %d: number of detected Vafpress configurations. */
        echo '<span class="pressundo-target__count">' . esc_html(sprintf(_n('%d configuration', '%d configurations', count($configurations), 'pressundo-framework'), count($configurations))) . '</span></div>';

        if ($configurations === []) {
            echo '<div class="pressundo-callout pressundo-callout--review"><strong>' . esc_html__('Vafpress references detected, but no constructor configuration was resolved.', 'pressundo-framework') . '</strong><p>' . esc_html__('This usually means the configuration is assembled dynamically, stored behind a constant/helper, or initialized through a custom wrapper. These files remain candidates for manual analysis.', 'pressundo-framework') . '</p></div>';
            echo '<details class="pressundo-files"><summary>' . esc_html__('Show matched files', 'pressundo-framework') . '</summary><ul>';
            foreach (array_slice((array) ($scan['matches'] ?? []), 0, 100) as $match) {
                echo '<li><code>' . esc_html((string) ($match['relative'] ?? '')) . '</code></li>';
            }
            echo '</ul></details>';
        }

        foreach ($configurations as $configuration) {
            if (is_array($configuration)) {
                $this->renderConfiguration($configuration);
            }
        }

        $warnings = (array) ($discovery['warnings'] ?? []);
        if ($warnings !== []) {
            /* translators: %d: number of parser warnings. */
            echo '<details class="pressundo-warnings"><summary>' . esc_html(sprintf(_n('%d parser warning', '%d parser warnings', count($warnings), 'pressundo-framework'), count($warnings))) . '</summary><ul>';
            foreach (array_slice($warnings, 0, 50) as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul></details>';
        }

        echo '</section>';
    }

    /** @param array<string,mixed> $configuration */
    private function renderConfiguration(array $configuration): void
    {
        $report = (array) ($configuration['report'] ?? []);
        $config = (array) ($configuration['config'] ?? []);
        $score = (int) ($configuration['compatibility'] ?? 0);
        $kind = (string) ($configuration['kind'] ?? 'configuration');
        $label = $kind === 'options'
            ? (string) ($config['page_title'] ?? $config['option_key'] ?? __('Options panel', 'pressundo-framework'))
            : (string) ($config['title'] ?? $config['id'] ?? __('Metabox', 'pressundo-framework'));

        echo '<article class="pressundo-config">';
        echo '<div class="pressundo-config__head"><div><span class="pressundo-kicker">' . esc_html($kind) . '</span><h4>' . esc_html($label) . '</h4>';
        echo '<p><code>' . esc_html((string) ($configuration['source_relative'] ?? '')) . '</code>';
        if (! empty($configuration['template_relative'])) {
            echo ' &rarr; <code>' . esc_html((string) $configuration['template_relative']) . '</code>';
        }
        echo '</p></div>';
        echo '<div class="pressundo-config__score"><strong>' . esc_html((string) $score) . '%</strong><span>' . esc_html__('compatible', 'pressundo-framework') . '</span></div></div>';

        if (empty($configuration['resolved']) || ! is_array($configuration['schema'] ?? null)) {
            echo '<div class="pressundo-callout pressundo-callout--manual"><strong>' . esc_html__('Manual analysis required', 'pressundo-framework') . '</strong><p>' . esc_html__('The Vafpress constructor was found, but its template could not be converted safely without executing legacy PHP.', 'pressundo-framework') . '</p></div>';
            echo '</article>';
            return;
        }

        echo '<div class="pressundo-config__stats">';
        $this->miniStat((string) ($report['automatic'] ?? 0), __('Automatic', 'pressundo-framework'), 'automatic');
        $this->miniStat((string) ($report['review'] ?? 0), __('Review', 'pressundo-framework'), 'review');
        $this->miniStat((string) ($report['manual'] ?? 0), __('Manual', 'pressundo-framework'), 'manual');
        echo '</div>';

        $configWarnings = (array) ($configuration['warnings'] ?? []);
        if ($configWarnings !== []) {
            echo '<div class="pressundo-callout pressundo-callout--review"><strong>' . esc_html__('Safe-parser review required', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($configWarnings, 0, 12) as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul></div>';
        }

        $this->renderReadiness((array) ($configuration['readiness'] ?? []));
        $this->renderOverrideSuggestions($configuration);
        $this->renderCutoverPlan((array) ($configuration['cutover'] ?? []));
        $this->renderTakeoverPreview($configuration);
        $this->renderMigrationPlan((array) ($configuration['plan'] ?? []));
        $shadow = (array) ($configuration['shadow_data'] ?? []);
        $this->renderShadowData($shadow);
        $this->renderShadowSave((array) ($configuration['shadow_save'] ?? []));
        $this->renderShadowEditor($configuration);

        echo '<details class="pressundo-schema-details"><summary>' . esc_html__('Preview normalized schema', 'pressundo-framework') . '</summary>';
        echo $this->previewRenderer->render((array) $configuration['schema']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values.
        echo '</details>';
        echo '<details class="pressundo-schema-details"><summary>' . esc_html__('Preview modern renderer', 'pressundo-framework') . '</summary>';
        echo '<p class="pressundo-preview-note">' . esc_html__('Controls are disabled. When legacy data is available, this preview shows the sampled stored values through the modern renderer.', 'pressundo-framework') . '</p>';
        echo $this->formRenderer->render((array) $configuration['schema'], (array) ($shadow['sample_values'] ?? []), true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values.
        echo '</details>';
        echo '</article>';
    }


    private function handleMigrationOverride(): void
    {
        check_admin_referer('pressundo_framework_override');

        $kind = sanitize_key((string) ($_POST['pressundo_override_kind'] ?? ''));
        $key = sanitize_key((string) ($_POST['pressundo_override_key'] ?? ''));
        if ($kind === '' || $key === '') {
            $this->overrideNotice = __('Migration override was not changed because its identifier was missing.', 'pressundo-framework');
            return;
        }

        if (isset($_POST['pressundo_remove_override'])) {
            $ok = $this->overrideRepository->remove($kind, $key);
            $this->overrideNotice = $ok
                ? __('Migration override removed. The analyzer was refreshed.', 'pressundo-framework')
                : __('Migration override could not be removed.', 'pressundo-framework');
            return;
        }

        $target = sanitize_key((string) ($_POST['pressundo_override_target'] ?? ''));
        $ok = $kind === 'field_type'
            ? $this->overrideRepository->saveFieldType($key, $target)
            : ($kind === 'datasource' ? $this->overrideRepository->saveDatasource($key, $target) : false);

        $this->overrideNotice = $ok
            ? __('Migration override saved. The analyzer was refreshed with the explicit rule.', 'pressundo-framework')
            : __('Migration override was rejected because the selected mapping is not allowed.', 'pressundo-framework');
    }

    private function renderOverrideRegistry(): void
    {
        $overrides = $this->overrideRepository->all();
        echo '<details class="pressundo-schema-details pressundo-overrides"><summary>' . esc_html__('Migration override registry', 'pressundo-framework') . '</summary>';
        echo '<p class="pressundo-plan__note">' . esc_html__('Overrides are explicit, reversible migration rules. They never execute legacy callbacks. A field-type rule maps an unknown legacy field to a modern Pressundo type; a datasource rule maps a custom legacy function name to a known WordPress-backed resolver.', 'pressundo-framework') . '</p>';

        if ($overrides['field_types'] === [] && $overrides['datasources'] === []) {
            echo '<p>' . esc_html__('No migration overrides are currently registered.', 'pressundo-framework') . '</p></details>';
            return;
        }

        echo '<div class="pressundo-override-list">';
        foreach ($overrides['field_types'] as $legacy => $target) {
            $this->renderAppliedOverride('field_type', (string) $legacy, (string) $target, __('Field type', 'pressundo-framework'));
        }
        foreach ($overrides['datasources'] as $legacy => $target) {
            $label = $this->overrideRepository->datasourceTargets()[$target] ?? $target;
            $this->renderAppliedOverride('datasource', (string) $legacy, (string) $label, __('Datasource', 'pressundo-framework'));
        }
        echo '</div></details>';
    }

    private function renderAppliedOverride(string $kind, string $legacy, string $target, string $label): void
    {
        echo '<div class="pressundo-override-row"><div><span class="pressundo-kicker">' . esc_html($label) . '</span><strong><code>' . esc_html($legacy) . '</code> → <code>' . esc_html($target) . '</code></strong></div>';
        echo '<form method="post">';
        wp_nonce_field('pressundo_framework_override');
        echo '<input type="hidden" name="pressundo_override_kind" value="' . esc_attr($kind) . '">';
        echo '<input type="hidden" name="pressundo_override_key" value="' . esc_attr($legacy) . '">';
        submit_button(__('Remove override', 'pressundo-framework'), 'secondary small', 'pressundo_remove_override', false);
        echo '</form></div>';
    }

    /** @param array<string,mixed> $configuration */
    private function renderOverrideSuggestions(array $configuration): void
    {
        $report = (array) ($configuration['report'] ?? []);
        $fieldTypeBlockers = [];
        $datasourceBlockers = [];

        foreach ((array) ($report['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $status = (string) ($field['status'] ?? '');
            $legacyType = sanitize_key((string) ($field['legacy_type'] ?? ''));
            $targetType = (string) ($field['target_type'] ?? '');
            if ($status === 'manual' && $legacyType !== '' && $targetType === 'legacy_unknown') {
                $fieldTypeBlockers[$legacyType] = true;
            }

            $source = (string) ($field['dynamic_source'] ?? '');
            if ($status === 'review' && str_starts_with($source, 'function:')) {
                $function = sanitize_key(substr($source, strlen('function:')));
                if ($function !== '') {
                    $datasourceBlockers[$function] = true;
                }
            }
        }

        if ($fieldTypeBlockers === [] && $datasourceBlockers === []) {
            return;
        }

        echo '<details class="pressundo-schema-details pressundo-override-suggestions" open><summary>' . esc_html__('Resolve migration blockers', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-callout pressundo-callout--review"><strong>' . esc_html__('Explicit administrator mapping required', 'pressundo-framework') . '</strong><p>' . esc_html__('Pressundo will not guess these mappings. Choose a rule only when you have verified what the old field or datasource represents. The rule is stored separately and can be removed at any time.', 'pressundo-framework') . '</p></div>';

        foreach (array_keys($datasourceBlockers) as $function) {
            echo '<div class="pressundo-override-editor"><div><strong>' . esc_html__('Custom datasource', 'pressundo-framework') . '</strong><code>' . esc_html($function) . '</code></div>';
            echo '<form method="post">';
            wp_nonce_field('pressundo_framework_override');
            echo '<input type="hidden" name="pressundo_override_kind" value="datasource"><input type="hidden" name="pressundo_override_key" value="' . esc_attr($function) . '">';
            echo '<select name="pressundo_override_target" required><option value="">' . esc_html__('Choose a safe resolver…', 'pressundo-framework') . '</option>';
            foreach ($this->overrideRepository->datasourceTargets() as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select> ';
            submit_button(__('Save datasource rule', 'pressundo-framework'), 'secondary', 'pressundo_save_override', false);
            echo '</form></div>';
        }

        foreach (array_keys($fieldTypeBlockers) as $legacyType) {
            echo '<div class="pressundo-override-editor"><div><strong>' . esc_html__('Unknown legacy field type', 'pressundo-framework') . '</strong><code>' . esc_html($legacyType) . '</code></div>';
            echo '<form method="post">';
            wp_nonce_field('pressundo_framework_override');
            echo '<input type="hidden" name="pressundo_override_kind" value="field_type"><input type="hidden" name="pressundo_override_key" value="' . esc_attr($legacyType) . '">';
            echo '<select name="pressundo_override_target" required><option value="">' . esc_html__('Choose modern field type…', 'pressundo-framework') . '</option>';
            foreach ($this->overrideRepository->fieldTargets() as $target) {
                echo '<option value="' . esc_attr($target) . '">' . esc_html($target) . '</option>';
            }
            echo '</select> ';
            submit_button(__('Save field-type rule', 'pressundo-framework'), 'secondary', 'pressundo_save_override', false);
            echo '</form></div>';
        }
        echo '</details>';
    }

    /** @param array<string,mixed> $configuration */
    private function renderTakeoverPreview(array $configuration): void
    {
        $preview = (array) ($configuration['takeover_preview'] ?? []);
        if ($preview === []) {
            return;
        }

        $ready = ($preview['status'] ?? '') === 'ready';
        echo '<details class="pressundo-schema-details pressundo-takeover-preview"><summary>' . esc_html__('Runtime takeover preview', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-callout pressundo-callout--' . esc_attr($ready ? 'automatic' : 'manual') . '"><strong>';
        echo esc_html($ready ? __('Replacement renderer can be mounted in preview', 'pressundo-framework') : __('Takeover preview blocked', 'pressundo-framework'));
        echo '</strong><p>' . esc_html((string) ($preview['message'] ?? '')) . '</p></div>';

        echo '<div class="pressundo-plan__storage">';
        echo '<div><span>' . esc_html__('Storage contract', 'pressundo-framework') . '</span><strong><code>' . esc_html((string) ($preview['storage_type'] ?? '')) . ':' . esc_html((string) ($preview['storage_key'] ?? '')) . '</code></strong></div>';
        echo '<div><span>' . esc_html__('Schema fingerprint', 'pressundo-framework') . '</span><strong><code>' . esc_html((string) ($preview['schema_fingerprint'] ?? '')) . '</code></strong></div>';
        echo '<div><span>' . esc_html__('Storage fingerprint', 'pressundo-framework') . '</span><strong><code>' . esc_html((string) ($preview['storage_fingerprint'] ?? '')) . '</code></strong></div>';
        echo '<div><span>' . esc_html__('Dual-read verification', 'pressundo-framework') . '</span><strong>' . esc_html(! empty($preview['dual_read_verified']) ? __('Passed', 'pressundo-framework') : __('Not passed', 'pressundo-framework')) . '</strong></div>';
        echo '<div><span>' . esc_html__('Detach legacy hooks', 'pressundo-framework') . '</span><strong>' . esc_html__('Disabled in 0.10', 'pressundo-framework') . '</strong></div></div>';

        $registration = (array) ($preview['registration'] ?? []);
        if ($registration !== []) {
            echo '<details class="pressundo-schema-details"><summary>' . esc_html__('Proposed modern registration', 'pressundo-framework') . '</summary><dl class="pressundo-takeover-registration">';
            foreach ($registration as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', array_map('strval', $value));
                }
                echo '<div><dt>' . esc_html((string) $key) . '</dt><dd><code>' . esc_html((string) $value) . '</code></dd></div>';
            }
            echo '</dl></details>';
        }

        if ($ready && is_array($configuration['schema'] ?? null)) {
            $values = (array) ($configuration['shadow_data']['sample_values'] ?? []);
            echo '<div class="pressundo-takeover-mounted"><div class="pressundo-takeover-mounted__head"><strong>' . esc_html__('Mounted Pressundo renderer preview', 'pressundo-framework') . '</strong><span>' . esc_html__('READ ONLY — legacy hooks are still untouched', 'pressundo-framework') . '</span></div>';
            echo $this->formRenderer->render((array) $configuration['schema'], $values, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values.
            echo '</div>';
        }
        echo '</details>';
    }

    /** @param array<string,mixed> $readiness */
    private function renderReadiness(array $readiness): void
    {
        if ($readiness === []) {
            return;
        }
        $status = (string) ($readiness['status'] ?? 'blocked');
        $label = (string) ($readiness['label'] ?? 'Blocked');
        $reasons = (array) ($readiness['reasons'] ?? []);
        echo '<div class="pressundo-readiness"><span class="pressundo-status pressundo-status--' . esc_attr($status) . '">' . esc_html($label) . '</span><strong>' . esc_html__('Migration readiness', 'pressundo-framework') . '</strong>';
        if ($reasons !== []) {
            echo '<span>' . esc_html((string) $reasons[0]) . '</span>';
        }
        echo '</div>';
        if (count($reasons) > 1) {
            echo '<details class="pressundo-schema-details"><summary>' . esc_html__('Readiness details', 'pressundo-framework') . '</summary><ul>';
            foreach ($reasons as $reason) {
                echo '<li>' . esc_html((string) $reason) . '</li>';
            }
            echo '</ul></details>';
        }
    }

    /** @param array<string,mixed> $cutover */
    private function renderCutoverPlan(array $cutover): void
    {
        if ($cutover === []) {
            return;
        }
        $candidate = ($cutover['status'] ?? '') === 'candidate';
        echo '<details class="pressundo-schema-details"><summary>' . esc_html__('Runtime replacement preparation', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-callout pressundo-callout--' . esc_attr($candidate ? 'review' : 'manual') . '"><strong>';
        echo esc_html($candidate ? __('Cutover candidate', 'pressundo-framework') : __('Cutover blocked', 'pressundo-framework'));
        echo '</strong><p>' . esc_html((string) ($cutover['message'] ?? '')) . '</p></div>';
        echo '<p class="pressundo-plan__note"><strong>' . esc_html__('Legacy runtime currently loaded:', 'pressundo-framework') . '</strong> ' . esc_html(! empty($cutover['runtime_loaded']) ? __('Yes', 'pressundo-framework') : __('No', 'pressundo-framework')) . '</p>';
        echo '<ol class="pressundo-plan__steps">';
        foreach ((array) ($cutover['steps'] ?? []) as $step) {
            echo '<li>' . esc_html((string) $step) . '</li>';
        }
        echo '</ol>';
        echo '<p class="pressundo-plan__note"><strong>' . esc_html__('Automatic Vafpress disable:', 'pressundo-framework') . '</strong> ' . esc_html__('Not enabled in normal Free mode.', 'pressundo-framework') . '</p>';
        echo '</details>';
    }

    /** @param array<string,mixed> $shadow */
    private function renderShadowData(array $shadow): void
    {
        if ($shadow === []) {
            return;
        }

        $status = (string) ($shadow['status'] ?? 'unavailable');
        $score = (int) ($shadow['compatibility'] ?? 0);
        $samples = (int) ($shadow['samples'] ?? 0);
        $statusLabel = match ($status) {
            'ready' => __('Ready', 'pressundo-framework'),
            'review' => __('Review', 'pressundo-framework'),
            'blocked' => __('Blocked', 'pressundo-framework'),
            'no_data' => __('No data', 'pressundo-framework'),
            default => __('Unavailable', 'pressundo-framework'),
        };
        $statusClass = match ($status) {
            'ready', 'no_data' => 'automatic',
            'review' => 'review',
            default => 'manual',
        };

        echo '<details class="pressundo-schema-details pressundo-shadow" open><summary>' . esc_html__('Shadow data compatibility', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-shadow__head"><div><span class="pressundo-status pressundo-status--' . esc_attr($statusClass) . '">' . esc_html($statusLabel) . '</span>';
        echo '<strong>' . esc_html((string) $score) . '%</strong><span>' . esc_html__('data compatibility', 'pressundo-framework') . '</span></div>';
        echo '<span class="pressundo-shadow__readonly">' . esc_html__('Read only', 'pressundo-framework') . '</span></div>';

        echo '<div class="pressundo-shadow__stats">';
        $this->shadowStat((string) ($shadow['compatible'] ?? 0), __('Compatible', 'pressundo-framework'));
        $this->shadowStat((string) ($shadow['normalized'] ?? 0), __('Normalizable', 'pressundo-framework'));
        $this->shadowStat((string) ($shadow['missing'] ?? 0), __('Missing', 'pressundo-framework'));
        $this->shadowStat((string) ($shadow['conflicts'] ?? 0), __('Conflicts', 'pressundo-framework'));
        $this->shadowStat((string) $samples, __('Samples', 'pressundo-framework'));
        echo '</div>';

        if (! empty($shadow['message'])) {
            echo '<p class="pressundo-plan__note">' . esc_html((string) $shadow['message']) . '</p>';
        }
        $sampleIds = array_values(array_filter(array_map('intval', (array) ($shadow['sample_ids'] ?? []))));
        if ($sampleIds !== []) {
            echo '<p class="pressundo-shadow__samples"><strong>' . esc_html__('Sample post IDs:', 'pressundo-framework') . '</strong> <code>' . esc_html(implode(', ', $sampleIds)) . '</code></p>';
        }

        $normalizations = (array) ($shadow['normalizations'] ?? []);
        if ($normalizations !== []) {
            echo '<div class="pressundo-callout pressundo-callout--review"><strong>' . esc_html__('Safe normalizations detected', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($normalizations, 0, 12) as $normalization) {
                echo '<li>' . esc_html((string) $normalization) . '</li>';
            }
            echo '</ul></div>';
        }

        $issues = (array) ($shadow['issues'] ?? []);
        if ($issues !== []) {
            $class = ((int) ($shadow['conflicts'] ?? 0)) > 0 ? 'manual' : 'review';
            echo '<div class="pressundo-callout pressundo-callout--' . esc_attr($class) . '"><strong>' . esc_html__('Data review details', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($issues, 0, 20) as $issue) {
                echo '<li>' . esc_html((string) $issue) . '</li>';
            }
            echo '</ul></div>';
        }
        echo '</details>';
    }

    /** @param array<string,mixed> $shadowSave */
    private function renderShadowSave(array $shadowSave): void
    {
        if ($shadowSave === []) {
            return;
        }

        $status = (string) ($shadowSave['status'] ?? 'unavailable');
        $statusLabel = match ($status) {
            'pass' => __('Pass', 'pressundo-framework'),
            'blocked' => __('Blocked', 'pressundo-framework'),
            'failed' => __('Failed', 'pressundo-framework'),
            'no_data' => __('No data', 'pressundo-framework'),
            default => __('Unavailable', 'pressundo-framework'),
        };
        $statusClass = match ($status) {
            'pass' => 'automatic',
            'blocked', 'no_data' => 'review',
            default => 'manual',
        };

        echo '<details class="pressundo-schema-details pressundo-roundtrip" open><summary>' . esc_html__('Shadow save round-trip', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-shadow__head"><div><span class="pressundo-status pressundo-status--' . esc_attr($statusClass) . '">' . esc_html($statusLabel) . '</span>';
        echo '<strong>' . esc_html(! empty($shadowSave['payloads_identical']) ? '100%' : '0%') . '</strong><span>' . esc_html__('storage round-trip', 'pressundo-framework') . '</span></div>';
        echo '<span class="pressundo-shadow__readonly">' . esc_html__('No writes performed', 'pressundo-framework') . '</span></div>';

        echo '<div class="pressundo-roundtrip__stats">';
        $this->shadowStat((string) ($shadowSave['compared_values'] ?? 0), __('Compared', 'pressundo-framework'));
        $this->shadowStat((string) ($shadowSave['storage_identical'] ?? 0), __('Storage identical', 'pressundo-framework'));
        $this->shadowStat((string) ($shadowSave['canonical_changes'] ?? 0), __('Core normalizations', 'pressundo-framework'));
        $this->shadowStat((string) ($shadowSave['storage_differences'] ?? 0), __('Storage differences', 'pressundo-framework'));
        $this->shadowStat((string) ($shadowSave['blocked_fields'] ?? 0), __('Blocked fields', 'pressundo-framework'));
        echo '</div>';

        if (! empty($shadowSave['message'])) {
            echo '<p class="pressundo-plan__note">' . esc_html((string) $shadowSave['message']) . '</p>';
        }

        $changes = (array) ($shadowSave['canonical_change_details'] ?? []);
        if ($changes !== []) {
            echo '<div class="pressundo-callout pressundo-callout--review"><strong>' . esc_html__('Canonical normalization with legacy storage preservation', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($changes, 0, 20) as $change) {
                echo '<li>' . esc_html((string) $change) . '</li>';
            }
            echo '</ul></div>';
        }

        $blocked = (array) ($shadowSave['blocked'] ?? []);
        if ($blocked !== []) {
            echo '<div class="pressundo-callout pressundo-callout--manual"><strong>' . esc_html__('Storage gate blockers', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($blocked, 0, 20) as $blocker) {
                echo '<li>' . esc_html((string) $blocker) . '</li>';
            }
            echo '</ul></div>';
        }

        $differences = (array) ($shadowSave['differences'] ?? []);
        if ($differences !== []) {
            echo '<div class="pressundo-callout pressundo-callout--manual"><strong>' . esc_html__('Round-trip differences', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($differences, 0, 30) as $difference) {
                echo '<li>' . esc_html((string) $difference) . '</li>';
            }
            echo '</ul></div>';
        }

        $hashes = (array) ($shadowSave['hashes'] ?? []);
        if ($hashes !== []) {
            echo '<div class="pressundo-roundtrip__hashes"><strong>' . esc_html__('Payload fingerprints', 'pressundo-framework') . '</strong>';
            foreach (array_slice($hashes, 0, 10) as $hash) {
                if (! is_array($hash)) {
                    continue;
                }
                echo '<div><code>' . esc_html((string) ($hash['sample'] ?? 'sample')) . '</code><span>';
                echo esc_html((string) ($hash['legacy'] ?? '')) . ' → ' . esc_html((string) ($hash['shadow'] ?? ''));
                echo '</span><strong>' . esc_html(! empty($hash['match']) ? __('Match', 'pressundo-framework') : __('Different', 'pressundo-framework')) . '</strong></div>';
            }
            echo '</div>';
        }

        $passed = ! empty($shadowSave['write_gate_passed']);
        echo '<div class="pressundo-write-gate pressundo-write-gate--' . esc_attr($passed ? 'pass' : 'blocked') . '"><strong>';
        echo esc_html($passed ? __('Storage compatibility gate passed', 'pressundo-framework') : __('Storage compatibility gate remains closed', 'pressundo-framework'));
        echo '</strong><span>' . esc_html($passed
            ? __('The sampled legacy payload survives the modern round-trip unchanged. A controlled write can be offered if this storage mode is supported.', 'pressundo-framework')
            : __('Pressundo will not enable migrated writes until every blocking field and storage difference has been resolved.', 'pressundo-framework')) . '</span></div>';

        echo '</details>';
    }

    private function shadowStat(string $value, string $label): void
    {
        echo '<div><strong>' . esc_html($value) . '</strong><span>' . esc_html($label) . '</span></div>';
    }


    /** @param array<string,mixed> $plan */
    private function renderMigrationPlan(array $plan): void
    {
        if ($plan === []) {
            return;
        }
        $storage = (array) ($plan['storage'] ?? []);
        $ready = ! empty($plan['ready_for_shadow_mode']);
        $roundTripReady = ! empty($plan['shadow_save_gate_passed']);
        $writeReady = ! empty($plan['ready_for_write_migration']);
        echo '<details class="pressundo-schema-details pressundo-plan"><summary>' . esc_html__('Migration plan & data preservation', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-plan__storage"><div><span>' . esc_html__('Storage', 'pressundo-framework') . '</span><strong>' . esc_html((string) ($storage['type'] ?? 'unknown')) . '</strong></div>';
        echo '<div><span>' . esc_html__('Legacy key', 'pressundo-framework') . '</span><strong><code>' . esc_html((string) ($storage['key'] ?? '')) . '</code></strong></div>';
        echo '<div><span>' . esc_html__('Strategy', 'pressundo-framework') . '</span><strong>' . esc_html__('Preserve existing data in place', 'pressundo-framework') . '</strong></div>';
        echo '<div><span>' . esc_html__('Shadow mode', 'pressundo-framework') . '</span><strong>' . esc_html($ready ? __('Ready', 'pressundo-framework') : __('Blocked', 'pressundo-framework')) . '</strong></div>';
        echo '<div><span>' . esc_html__('Round-trip gate', 'pressundo-framework') . '</span><strong>' . esc_html($roundTripReady ? __('Passed', 'pressundo-framework') : __('Closed', 'pressundo-framework')) . '</strong></div>';
        echo '<div><span>' . esc_html__('Controlled write', 'pressundo-framework') . '</span><strong>' . esc_html($writeReady ? __('Available', 'pressundo-framework') : __('Unavailable', 'pressundo-framework')) . '</strong></div></div>';
        if (! empty($storage['notes'])) {
            echo '<p class="pressundo-plan__note">' . esc_html((string) $storage['notes']) . '</p>';
        }
        $blockers = (array) ($plan['blockers'] ?? []);
        if ($blockers !== []) {
            echo '<div class="pressundo-callout pressundo-callout--manual"><strong>' . esc_html__('Current blockers', 'pressundo-framework') . '</strong><ul>';
            foreach ($blockers as $blocker) {
                echo '<li>' . esc_html((string) $blocker) . '</li>';
            }
            echo '</ul></div>';
        }
        echo '<ol class="pressundo-plan__steps">';
        foreach ((array) ($plan['steps'] ?? []) as $step) {
            if (is_array($step)) {
                echo '<li>' . esc_html((string) ($step['label'] ?? '')) . '</li>';
            }
        }
        echo '</ol></details>';
    }

    /** @param array<string,mixed> $report */
    private function handleShadowSubmission(array $report): void
    {
        $configurationId = isset($_POST['pressundo_configuration_id']) ? sanitize_key(wp_unslash($_POST['pressundo_configuration_id'])) : '';
        if ($configurationId === '') {
            return;
        }

        check_admin_referer('pressundo_framework_shadow_' . $configurationId, 'pressundo_shadow_nonce');

        foreach ((array) ($report['targets'] ?? []) as $target) {
            if (! is_array($target)) {
                continue;
            }
            $discovery = (array) ($target['discovery'] ?? []);
            foreach ((array) ($discovery['configurations'] ?? []) as $configuration) {
                if (! is_array($configuration) || $this->configurationId($configuration) !== $configurationId) {
                    continue;
                }

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Field-aware sanitization is performed by the simulator/service.
                $submitted = isset($_POST['pressundo_values']) ? wp_unslash($_POST['pressundo_values']) : [];
                $submitted = is_array($submitted) ? $submitted : [];
                $this->shadowSubmissionResults[$configurationId] = $this->shadowSubmissionSimulator->simulate($configuration, $submitted);
                return;
            }
        }

        $this->shadowSubmissionResults[$configurationId] = [
            'status' => 'rejected',
            'message' => __('The submitted configuration could not be matched to the current scan and was not processed.', 'pressundo-framework'),
            'write_performed' => false,
        ];
    }

    /** @param array<string,mixed> $report */
    private function handleControlledWrite(array $report): void
    {
        $configurationId = isset($_POST['pressundo_configuration_id']) ? sanitize_key(wp_unslash($_POST['pressundo_configuration_id'])) : '';
        if ($configurationId === '') {
            return;
        }

        check_admin_referer('pressundo_framework_write_' . $configurationId, 'pressundo_write_nonce');

        $confirmed = isset($_POST['pressundo_confirm_write']) ? sanitize_key(wp_unslash($_POST['pressundo_confirm_write'])) : '';
        if ($confirmed !== '1') {
            $this->controlledWriteResults[$configurationId] = [
                'status' => 'confirmation_required',
                'write_performed' => false,
                'message' => __('Controlled write was not performed because the explicit confirmation checkbox was not selected.', 'pressundo-framework'),
            ];
            return;
        }

        foreach ((array) ($report['targets'] ?? []) as $target) {
            if (! is_array($target)) {
                continue;
            }
            foreach ((array) (($target['discovery']['configurations'] ?? [])) as $configuration) {
                if (! is_array($configuration) || $this->configurationId($configuration) !== $configurationId) {
                    continue;
                }

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Field-aware sanitization is performed by the simulator/service.
                $submitted = isset($_POST['pressundo_values']) ? wp_unslash($_POST['pressundo_values']) : [];
                $submitted = is_array($submitted) ? $submitted : [];
                $postId = isset($_POST['pressundo_sample_id']) ? absint(wp_unslash($_POST['pressundo_sample_id'])) : 0;
                $this->controlledWriteResults[$configurationId] = $this->controlledWriteService->apply(
                    $configuration,
                    $submitted,
                    $configurationId,
                    $postId
                );
                return;
            }
        }

        $this->controlledWriteResults[$configurationId] = [
            'status' => 'rejected',
            'write_performed' => false,
            'message' => __('The submitted configuration could not be matched to the current scan and no write was performed.', 'pressundo-framework'),
        ];
    }

    private function handleRollback(): void
    {
        $snapshotId = isset($_POST['pressundo_snapshot_id']) ? sanitize_key(wp_unslash($_POST['pressundo_snapshot_id'])) : '';
        if ($snapshotId === '') {
            $this->rollbackResult = [
                'status' => 'missing',
                'message' => __('Rollback snapshot ID is missing.', 'pressundo-framework'),
            ];
            return;
        }

        check_admin_referer('pressundo_framework_rollback_' . $snapshotId);
        $this->rollbackResult = $this->controlledWriteService->rollback($snapshotId);
    }

    /** @param array<string,mixed> $configuration */
    private function renderShadowEditor(array $configuration): void
    {
        $shadow = (array) ($configuration['shadow_data'] ?? []);
        if (empty($configuration['resolved']) || ! is_array($configuration['schema'] ?? null) || (int) ($shadow['samples'] ?? 0) < 1) {
            return;
        }

        $configurationId = $this->configurationId($configuration);
        $result = $this->shadowSubmissionResults[$configurationId] ?? null;
        $writeResult = $this->controlledWriteResults[$configurationId] ?? null;
        $baselinePassed = ! empty($configuration['shadow_save']['write_gate_passed']);
        $writeReady = ! empty($configuration['plan']['ready_for_write_migration']);
        $sampleIds = array_values(array_filter(array_map('intval', (array) ($shadow['sample_ids'] ?? []))));
        $sampleId = (int) ($sampleIds[0] ?? 0);

        echo '<details class="pressundo-schema-details pressundo-shadow-editor" open><summary>' . esc_html__('Migration editor', 'pressundo-framework') . '</summary>';
        echo '<div class="pressundo-shadow-editor__intro"><div><strong>' . esc_html__('Edit the modern controls, simulate first, then optionally perform a guarded write.', 'pressundo-framework') . '</strong>';
        echo '<p>' . esc_html__('Automatic fields are editable. Review/manual fields stay locked and are preserved from legacy storage. Shadow simulation is read-only. Controlled write is available only when the baseline gate passes and requires explicit confirmation.', 'pressundo-framework') . '</p></div>';
        echo '<span class="pressundo-shadow__readonly">' . esc_html__('GUARDED MIGRATION', 'pressundo-framework') . '</span></div>';

        if (is_array($result)) {
            $this->renderShadowSubmissionResult($result);
        }
        if (is_array($writeResult)) {
            $this->renderControlledWriteResult($writeResult);
        }

        if (is_array($result) && is_array($result['form_values'] ?? null)) {
            // Keep the submitted modern-form state after a shadow simulation. This
            // preserves stable repeater row identities (including new-* rows) and
            // sortable ordering for the subsequent controlled-write request.
            $formValues = (array) $result['form_values'];
        } elseif (is_array($writeResult) && is_array($writeResult['simulation']['form_values'] ?? null)) {
            $formValues = (array) $writeResult['simulation']['form_values'];
        } elseif (is_array($result) && is_array($result['payload'] ?? null)) {
            $formValues = (array) $result['payload'];
        } elseif (is_array($writeResult) && is_array($writeResult['simulation']['payload'] ?? null)) {
            $formValues = (array) $writeResult['simulation']['payload'];
        } else {
            $formValues = (array) ($shadow['sample_values'] ?? []);
        }

        echo '<form method="post" class="pressundo-shadow-edit-form">';
        wp_nonce_field('pressundo_framework_shadow_' . $configurationId, 'pressundo_shadow_nonce');
        wp_nonce_field('pressundo_framework_write_' . $configurationId, 'pressundo_write_nonce');
        echo '<input type="hidden" name="pressundo_configuration_id" value="' . esc_attr($configurationId) . '">';
        echo '<input type="hidden" name="pressundo_sample_id" value="' . esc_attr((string) $sampleId) . '">';
        $renderedForm = $this->formRenderer->render(
            (array) $configuration['schema'],
            $formValues,
            false,
            'pressundo_values',
            true
        );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SchemaFormRenderer escapes all dynamic values before returning markup.
        echo $renderedForm;

        echo '<div class="pressundo-shadow-edit-form__actions">';
        submit_button(__('Simulate edited payload', 'pressundo-framework'), 'secondary', 'pressundo_shadow_submit', false);
        echo '<span>' . esc_html($baselinePassed
            ? __('Baseline storage gate passed. Simulation remains read-only until you explicitly choose the controlled write action below.', 'pressundo-framework')
            : __('Baseline storage gate is closed. You can test safe fields, but controlled writes remain unavailable.', 'pressundo-framework')) . '</span>';
        echo '</div>';

        if ($writeReady) {
            echo '<div class="pressundo-controlled-write-box">';
            echo '<div><strong>' . esc_html__('Controlled database write', 'pressundo-framework') . '</strong><p>' . esc_html__('Pressundo will re-scan and re-validate the submission, capture an encrypted rollback snapshot, write only the sampled legacy storage record, then read it back and verify the fingerprint. Verification failure triggers an automatic rollback attempt.', 'pressundo-framework') . '</p></div>';
            echo '<label class="pressundo-write-confirm"><input type="checkbox" name="pressundo_confirm_write" value="1"> ' . esc_html__('I understand this action will modify the legacy database value and I want Pressundo to create a rollback snapshot and apply these changes.', 'pressundo-framework') . '</label>';
            submit_button(__('Apply controlled write', 'pressundo-framework'), 'primary pressundo-write-button', 'pressundo_controlled_write', false);
            echo '</div>';
        } else {
            echo '<div class="pressundo-callout pressundo-callout--review"><strong>' . esc_html__('Controlled write unavailable', 'pressundo-framework') . '</strong><p>' . esc_html__('The baseline storage gate or supported-storage requirement has not passed. Shadow simulation remains available.', 'pressundo-framework') . '</p></div>';
        }

        echo '</form></details>';
    }

    /** @param array<string,mixed> $result */
    private function renderShadowSubmissionResult(array $result): void
    {
        $status = (string) ($result['status'] ?? 'unavailable');
        $class = match ($status) {
            'pass' => 'automatic',
            'blocked' => 'review',
            default => 'manual',
        };
        $label = match ($status) {
            'pass' => __('Pass', 'pressundo-framework'),
            'blocked' => __('Blocked by baseline gate', 'pressundo-framework'),
            'rejected' => __('Rejected', 'pressundo-framework'),
            default => __('Unavailable', 'pressundo-framework'),
        };

        echo '<div class="pressundo-shadow-submit-result">';
        echo '<div class="pressundo-shadow-submit-result__head"><span class="pressundo-status pressundo-status--' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        echo '<strong>' . esc_html__('Shadow submission result', 'pressundo-framework') . '</strong><span>' . esc_html__('0 writes', 'pressundo-framework') . '</span></div>';

        if (! empty($result['message'])) {
            echo '<p>' . esc_html((string) $result['message']) . '</p>';
        }

        echo '<div class="pressundo-roundtrip__stats">';
        $this->shadowStat((string) ($result['submitted_fields'] ?? 0), __('Submitted', 'pressundo-framework'));
        $this->shadowStat((string) ($result['accepted_fields'] ?? 0), __('Accepted', 'pressundo-framework'));
        $this->shadowStat((string) ($result['changed_fields'] ?? 0), __('Changed', 'pressundo-framework'));
        $this->shadowStat((string) ($result['locked_fields'] ?? 0), __('Locked', 'pressundo-framework'));
        $this->shadowStat((string) ($result['rejected_fields'] ?? 0), __('Rejected', 'pressundo-framework'));
        echo '</div>';

        if (! empty($result['legacy_hash']) || ! empty($result['shadow_hash'])) {
            echo '<div class="pressundo-roundtrip__hashes"><strong>' . esc_html__('Proposed payload fingerprint', 'pressundo-framework') . '</strong><div><code>';
            echo esc_html((string) ($result['legacy_hash'] ?? '')) . '</code><span>→ ' . esc_html((string) ($result['shadow_hash'] ?? '')) . '</span><strong>';
            echo esc_html(! empty($result['payload_changed']) ? __('Changed', 'pressundo-framework') : __('Unchanged', 'pressundo-framework')) . '</strong></div></div>';
        }

        $changes = (array) ($result['changes'] ?? []);
        if ($changes !== []) {
            echo '<div class="pressundo-callout pressundo-callout--automatic"><strong>' . esc_html__('Proposed safe changes', 'pressundo-framework') . '</strong><ul>';
            foreach (array_slice($changes, 0, 30) as $change) {
                if (! is_array($change)) {
                    continue;
                }
                echo '<li><code>' . esc_html((string) ($change['path'] ?? 'field')) . '</code>: ' . esc_html((string) ($change['before'] ?? '')) . ' → ' . esc_html((string) ($change['after'] ?? '')) . '</li>';
            }
            echo '</ul></div>';
        }

        foreach ([
            'rejected' => [__('Rejected input', 'pressundo-framework'), 'manual'],
            'ignored' => [__('Ignored unknown input', 'pressundo-framework'), 'manual'],
            'locked' => [__('Preserved locked fields', 'pressundo-framework'), 'review'],
        ] as $key => [$title, $calloutClass]) {
            $items = (array) ($result[$key] ?? []);
            if ($items === []) {
                continue;
            }
            echo '<div class="pressundo-callout pressundo-callout--' . esc_attr($calloutClass) . '"><strong>' . esc_html($title) . '</strong><ul>';
            foreach (array_slice($items, 0, 20) as $item) {
                echo '<li>' . esc_html((string) $item) . '</li>';
            }
            echo '</ul></div>';
        }

        $gatePassed = ! empty($result['submission_gate_passed']);
        echo '<div class="pressundo-write-gate pressundo-write-gate--' . esc_attr($gatePassed ? 'pass' : 'blocked') . '"><strong>';
        echo esc_html($gatePassed ? __('Shadow submission gate passed', 'pressundo-framework') : __('Shadow submission gate remains closed', 'pressundo-framework'));
        echo '</strong><span>' . esc_html($gatePassed
            ? __('The submitted values passed sanitization and legacy serialization. Shadow simulation itself performed no database write; the separate controlled-write action may now be used when available.', 'pressundo-framework')
            : __('The proposed submission cannot enter the controlled-write pipeline until the baseline and submitted payload gates both pass.', 'pressundo-framework')) . '</span></div>';
        echo '</div>';
    }

    /** @param array<string,mixed> $result */
    private function renderControlledWriteResult(array $result): void
    {
        $status = (string) ($result['status'] ?? 'unavailable');
        $class = match ($status) {
            'success' => 'automatic',
            'no_change', 'verification_failed_rolled_back' => 'review',
            default => 'manual',
        };
        $label = match ($status) {
            'success' => __('Written + verified', 'pressundo-framework'),
            'no_change' => __('No change', 'pressundo-framework'),
            'stale' => __('Stale payload', 'pressundo-framework'),
            'confirmation_required' => __('Confirmation required', 'pressundo-framework'),
            'snapshot_failed' => __('Snapshot failed', 'pressundo-framework'),
            'write_failed' => __('Write failed', 'pressundo-framework'),
            'verification_failed_rolled_back' => __('Auto-rolled back', 'pressundo-framework'),
            'verification_failed' => __('Verification failed', 'pressundo-framework'),
            default => __('Blocked', 'pressundo-framework'),
        };

        echo '<div class="pressundo-controlled-result">';
        echo '<div class="pressundo-shadow-submit-result__head"><span class="pressundo-status pressundo-status--' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        echo '<strong>' . esc_html__('Controlled write result', 'pressundo-framework') . '</strong><span>' . esc_html(! empty($result['write_performed']) ? __('database write attempted', 'pressundo-framework') : __('0 writes', 'pressundo-framework')) . '</span></div>';

        if (! empty($result['message'])) {
            echo '<p>' . esc_html((string) $result['message']) . '</p>';
        }
        if (! empty($result['target'])) {
            echo '<p><strong>' . esc_html__('Target:', 'pressundo-framework') . '</strong> <code>' . esc_html((string) $result['target']) . '</code></p>';
        }
        if (! empty($result['before_hash']) || ! empty($result['after_hash'])) {
            echo '<div class="pressundo-roundtrip__hashes"><strong>' . esc_html__('Verified storage fingerprint', 'pressundo-framework') . '</strong><div><code>';
            echo esc_html((string) ($result['before_hash'] ?? '')) . '</code><span>→ ' . esc_html((string) ($result['after_hash'] ?? '')) . '</span><strong>' . esc_html($status === 'success' ? __('Verified', 'pressundo-framework') : __('Check status', 'pressundo-framework')) . '</strong></div></div>';
        }
        if (! empty($result['snapshot_id'])) {
            echo '<p class="pressundo-snapshot-id"><strong>' . esc_html__('Rollback snapshot:', 'pressundo-framework') . '</strong> <code>' . esc_html((string) $result['snapshot_id']) . '</code></p>';
        }
        echo '</div>';
    }

    /** @param array<string,mixed> $result */
    private function renderRollbackResult(array $result): void
    {
        $status = (string) ($result['status'] ?? 'failed');
        $success = $status === 'success';
        $review = in_array($status, ['stale', 'blocked'], true);
        $class = $success ? 'automatic' : ($review ? 'review' : 'manual');
        $title = $success ? __('Rollback completed', 'pressundo-framework') : __('Rollback result', 'pressundo-framework');

        echo '<div class="pressundo-callout pressundo-callout--' . esc_attr($class) . ' pressundo-rollback-result"><strong>' . esc_html($title) . '</strong>';
        if (! empty($result['message'])) {
            echo '<p>' . esc_html((string) $result['message']) . '</p>';
        }
        if (! empty($result['snapshot_id'])) {
            echo '<p><code>' . esc_html((string) $result['snapshot_id']) . '</code></p>';
        }
        if (! empty($result['actual_hash']) || ! empty($result['expected_hash'])) {
            echo '<p><code>' . esc_html((string) ($result['actual_hash'] ?? '')) . '</code> → <code>' . esc_html((string) ($result['expected_hash'] ?? '')) . '</code></p>';
        }
        echo '</div>';
    }

    private function renderSnapshotHistory(): void
    {
        $snapshots = $this->snapshotRepository->recent(10);
        if ($snapshots === []) {
            return;
        }

        echo '<details class="pressundo-schema-details pressundo-snapshot-history" open><summary>' . esc_html__('Migration history & rollback snapshots', 'pressundo-framework') . '</summary>';
        echo '<p class="pressundo-plan__note">' . esc_html__('Each controlled write creates an encrypted rollback point. The timeline shows applied and rolled-back migrations, fingerprints and update time. Live applied rollback points are never auto-trimmed, and rollback refuses to overwrite newer changes.', 'pressundo-framework') . '</p>';
        echo '<div class="pressundo-snapshot-list">';
        foreach ($snapshots as $snapshot) {
            $id = (string) ($snapshot['id'] ?? '');
            $state = (string) ($snapshot['state'] ?? 'unknown');
            $storageType = (string) ($snapshot['storage_type'] ?? '');
            $key = (string) ($snapshot['storage_key'] ?? '');
            $postId = (int) ($snapshot['post_id'] ?? 0);
            $target = $storageType === 'wp_option' ? 'option:' . $key : sprintf('post:%d meta:%s', $postId, $key);
            $stateClass = $state === 'applied' ? 'automatic' : (str_contains($state, 'rolled_back') ? 'review' : 'manual');

            echo '<div class="pressundo-snapshot-row">';
            echo '<div><span class="pressundo-status pressundo-status--' . esc_attr($stateClass) . '">' . esc_html($state) . '</span><strong><code>' . esc_html($id) . '</code></strong>';
            echo '<p><code>' . esc_html($target) . '</code></p>';
            echo '<small>' . esc_html((string) ($snapshot['before_hash'] ?? '')) . ' → ' . esc_html((string) ($snapshot['after_hash'] ?? '')) . '</small>';
            echo '<div class="pressundo-history-meta"><span>' . esc_html__('Created:', 'pressundo-framework') . ' ' . esc_html((string) ($snapshot['created_at'] ?? '')) . '</span>';
            if (! empty($snapshot['updated_at'])) {
                echo '<span>' . esc_html__('Updated:', 'pressundo-framework') . ' ' . esc_html((string) $snapshot['updated_at']) . '</span>';
            }
            echo '</div></div>';
            if ($state === 'applied' && $id !== '') {
                echo '<form method="post" class="pressundo-rollback-form">';
                wp_nonce_field('pressundo_framework_rollback_' . $id);
                echo '<input type="hidden" name="pressundo_snapshot_id" value="' . esc_attr($id) . '">';
                submit_button(__('Rollback this write', 'pressundo-framework'), 'secondary', 'pressundo_rollback', false);
                echo '</form>';
            }
            echo '</div>';
        }
        echo '</div></details>';
    }

    /** @param array<string,mixed> $configuration */
    private function configurationId(array $configuration): string
    {
        $config = (array) ($configuration['config'] ?? []);
        $identity = implode('|', [
            (string) ($configuration['kind'] ?? ''),
            (string) ($configuration['source_file'] ?? $configuration['source_relative'] ?? ''),
            (string) ($configuration['template_file'] ?? $configuration['template_relative'] ?? ''),
            (string) ($config['option_key'] ?? $config['id'] ?? ''),
        ]);
        return substr(sha1($identity), 0, 20);
    }

    private function stat(string $value, string $label): void
    {
        echo '<div><strong>' . esc_html($value) . '</strong><span>' . esc_html($label) . '</span></div>';
    }

    private function miniStat(string $value, string $label, string $status): void
    {
        echo '<div><span class="pressundo-status pressundo-status--' . esc_attr($status) . '">' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }
}
