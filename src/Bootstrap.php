<?php

declare(strict_types=1);

namespace Pressundo\Framework;

use Pressundo\Framework\Admin\AdminApp;
use Pressundo\Framework\Admin\FrameworkAdminPage;
use Pressundo\Framework\Admin\AssetManager;
use Pressundo\Framework\Api\Factory;
use Pressundo\Framework\Api\FieldFactory;
use Pressundo\Framework\Api\FieldValueProcessor;
use Pressundo\Framework\Api\OptionsResolver;
use Pressundo\Framework\Api\ConditionEvaluator;
use Pressundo\Framework\Core\Container;
use Pressundo\Framework\Field\CheckboxField;
use Pressundo\Framework\Field\SelectField;
use Pressundo\Framework\Field\TextField;
use Pressundo\Framework\Field\TextareaField;
use Pressundo\Framework\Field\ToggleField;
use Pressundo\Framework\Field\NumberField;
use Pressundo\Framework\Field\EmailField;
use Pressundo\Framework\Field\UrlField;
use Pressundo\Framework\Field\ColorField;
use Pressundo\Framework\Field\DateField;
use Pressundo\Framework\Field\TimeField;
use Pressundo\Framework\Field\RangeField;
use Pressundo\Framework\Field\RadioField;
use Pressundo\Framework\Field\MultiSelectField;
use Pressundo\Framework\Field\CheckboxGroupField;
use Pressundo\Framework\Field\MediaField;
use Pressundo\Framework\Field\GalleryField;
use Pressundo\Framework\Field\EditorField;
use Pressundo\Framework\Field\CodeField;
use Pressundo\Framework\Field\IconField;
use Pressundo\Framework\Field\SortableField;
use Pressundo\Framework\Field\NoticeField;
use Pressundo\Framework\Field\HtmlField;
use Pressundo\Framework\Field\ImageCheckboxGroupField;
use Pressundo\Framework\Field\ImageRadioField;
use Pressundo\Framework\Field\AdvancedField;
use Pressundo\Framework\Legacy\Vafpress\CompatibilityMatrix;
use Pressundo\Framework\Legacy\Vafpress\ConfigurationDiscovery;
use Pressundo\Framework\Legacy\Vafpress\Detector;
use Pressundo\Framework\Legacy\Vafpress\DynamicItemResolver;
use Pressundo\Framework\Legacy\Vafpress\MigrationOverrideRepository;
use Pressundo\Framework\Legacy\Vafpress\LegacyDefaults;
use Pressundo\Framework\Legacy\Vafpress\LegacyRuntimeSaveService;
use Pressundo\Framework\Legacy\Vafpress\LegacyTemplateLoader;
use Pressundo\Framework\Legacy\Vafpress\TemplateConverter;
use Pressundo\Framework\Migration\MigrationPlanBuilder;
use Pressundo\Framework\Migration\Progressive\ProgressiveMigrationService;
use Pressundo\Framework\Migration\Progressive\StorageAccessor;
use Pressundo\Framework\Migration\MigrationReadinessEvaluator;
use Pressundo\Framework\Migration\RuntimeCutoverPlanner;
use Pressundo\Framework\Migration\RuntimeTakeoverPreview;
use Pressundo\Framework\Migration\Scanner;
use Pressundo\Framework\Migration\ShadowDataAnalyzer;
use Pressundo\Framework\Migration\ShadowSaveComparator;
use Pressundo\Framework\Migration\ShadowSubmissionSimulator;
use Pressundo\Framework\Migration\SnapshotRepository;
use Pressundo\Framework\Migration\ControlledWriteService;
use Pressundo\Framework\Registry\FieldRegistry;
use Pressundo\Framework\Renderer\SchemaFormRenderer;
use Pressundo\Framework\Renderer\SchemaPreviewRenderer;
use Pressundo\Framework\Schema\SchemaNormalizer;
use Pressundo\Framework\Storage\OptionStore;
use Pressundo\Framework\Storage\PostMetaStore;

final class Bootstrap
{
    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->container = new Container();
        $this->registerServices();
        $this->registerFields();

        if (is_admin()) {
            if ($this->diagnosticsEnabled()) {
                $this->container->get(AdminApp::class)->register();
            } else {
                $this->container->get(FrameworkAdminPage::class)->register();
            }
        }

        do_action('pressundo_framework_loaded', $this);
    }

    public function container(): Container
    {
        if (! $this->booted) {
            $this->boot();
        }

        return $this->container;
    }

    private function registerServices(): void
    {
        $this->container->set(AssetManager::class, static fn (): AssetManager => new AssetManager());
        $this->container->set(FieldRegistry::class, static fn (): FieldRegistry => new FieldRegistry());
        $this->container->set(SchemaNormalizer::class, static fn (): SchemaNormalizer => new SchemaNormalizer());
        $this->container->set(OptionStore::class, static fn (): OptionStore => new OptionStore());
        $this->container->set(PostMetaStore::class, static fn (): PostMetaStore => new PostMetaStore());
        $this->container->set(StorageAccessor::class, static fn (): StorageAccessor => new StorageAccessor());
        $this->container->set(ProgressiveMigrationService::class, fn (Container $c): ProgressiveMigrationService => new ProgressiveMigrationService($c->get(StorageAccessor::class)));
        $this->container->set(Factory::class, fn (Container $c): Factory => new Factory($c->get(ProgressiveMigrationService::class)));
        $this->container->set(FieldFactory::class, static fn (): FieldFactory => new FieldFactory());
        $this->container->set(OptionsResolver::class, static fn (): OptionsResolver => new OptionsResolver());
        $this->container->set(ConditionEvaluator::class, static fn (): ConditionEvaluator => new ConditionEvaluator());
        $this->container->set(FieldValueProcessor::class, fn (Container $c): FieldValueProcessor => new FieldValueProcessor($c->get(FieldRegistry::class), $c->get(OptionsResolver::class)));
        $this->container->set(Detector::class, static fn (): Detector => new Detector());
        $this->container->set(MigrationOverrideRepository::class, static fn (): MigrationOverrideRepository => new MigrationOverrideRepository());
        $this->container->set(LegacyTemplateLoader::class, static fn (): LegacyTemplateLoader => new LegacyTemplateLoader());
        $this->container->set(LegacyDefaults::class, static fn (): LegacyDefaults => new LegacyDefaults());
        $this->container->set(LegacyRuntimeSaveService::class, fn (Container $c): LegacyRuntimeSaveService => new LegacyRuntimeSaveService($c->get(ShadowSubmissionSimulator::class), $c->get(LegacyDefaults::class)));
        $this->container->set(CompatibilityMatrix::class, fn (Container $c): CompatibilityMatrix => new CompatibilityMatrix($c->get(MigrationOverrideRepository::class)));
        $this->container->set(DynamicItemResolver::class, fn (Container $c): DynamicItemResolver => new DynamicItemResolver($c->get(MigrationOverrideRepository::class)));
        $this->container->set(TemplateConverter::class, fn (Container $c): TemplateConverter => new TemplateConverter($c->get(CompatibilityMatrix::class), $c->get(DynamicItemResolver::class)));
        $this->container->set(ConfigurationDiscovery::class, fn (Container $c): ConfigurationDiscovery => new ConfigurationDiscovery($c->get(TemplateConverter::class)));
        $this->container->set(MigrationPlanBuilder::class, static fn (): MigrationPlanBuilder => new MigrationPlanBuilder());
        $this->container->set(MigrationReadinessEvaluator::class, static fn (): MigrationReadinessEvaluator => new MigrationReadinessEvaluator());
        $this->container->set(RuntimeCutoverPlanner::class, static fn (): RuntimeCutoverPlanner => new RuntimeCutoverPlanner());
        $this->container->set(RuntimeTakeoverPreview::class, static fn (): RuntimeTakeoverPreview => new RuntimeTakeoverPreview());
        $this->container->set(ShadowDataAnalyzer::class, static fn (): ShadowDataAnalyzer => new ShadowDataAnalyzer());
        $this->container->set(ShadowSaveComparator::class, static fn (): ShadowSaveComparator => new ShadowSaveComparator());
        $this->container->set(ShadowSubmissionSimulator::class, fn (Container $c): ShadowSubmissionSimulator => new ShadowSubmissionSimulator($c->get(FieldValueProcessor::class)));
        $this->container->set(SnapshotRepository::class, static fn (): SnapshotRepository => new SnapshotRepository());
        $this->container->set(ControlledWriteService::class, fn (Container $c): ControlledWriteService => new ControlledWriteService($c->get(ShadowSubmissionSimulator::class), $c->get(SnapshotRepository::class)));
        $this->container->set(Scanner::class, fn (Container $c): Scanner => new Scanner($c->get(Detector::class), $c->get(ConfigurationDiscovery::class), $c->get(MigrationPlanBuilder::class), $c->get(ShadowDataAnalyzer::class), $c->get(ShadowSaveComparator::class), $c->get(MigrationReadinessEvaluator::class), $c->get(RuntimeCutoverPlanner::class), $c->get(RuntimeTakeoverPreview::class)));
        $this->container->set(SchemaPreviewRenderer::class, static fn (): SchemaPreviewRenderer => new SchemaPreviewRenderer());
        $this->container->set(SchemaFormRenderer::class, fn (Container $c): SchemaFormRenderer => new SchemaFormRenderer($c->get(FieldRegistry::class), $c->get(OptionsResolver::class), $c->get(ConditionEvaluator::class)));
        $this->container->set(FrameworkAdminPage::class, fn (Container $c): FrameworkAdminPage => new FrameworkAdminPage($c->get(AssetManager::class)));
        $this->container->set(AdminApp::class, fn (Container $c): AdminApp => new AdminApp($c->get(Scanner::class), $c->get(SchemaPreviewRenderer::class), $c->get(SchemaFormRenderer::class), $c->get(ShadowSubmissionSimulator::class), $c->get(ControlledWriteService::class), $c->get(SnapshotRepository::class), $c->get(MigrationOverrideRepository::class)));
    }

    private function diagnosticsEnabled(): bool
    {
        return defined('PRESSUNDO_FRAMEWORK_DEBUG') && PRESSUNDO_FRAMEWORK_DEBUG === true;
    }

    private function registerFields(): void
    {
        $registry = $this->container->get(FieldRegistry::class);
        $registry->register(new TextField());
        $registry->register(new TextareaField());
        $registry->register(new SelectField());
        $registry->register(new CheckboxField());
        $registry->register(new ToggleField());
        $registry->register(new NumberField());
        $registry->register(new EmailField());
        $registry->register(new UrlField());
        $registry->register(new ColorField());
        $registry->register(new DateField());
        $registry->register(new TimeField());
        $registry->register(new RangeField());
        $registry->register(new RadioField());
        $registry->register(new MultiSelectField());
        $registry->register(new CheckboxGroupField());
        $registry->register(new MediaField());
        $registry->register(new GalleryField());
        $registry->register(new EditorField());
        $registry->register(new CodeField());
        $registry->register(new IconField());
        $registry->register(new SortableField());
        $registry->register(new ImageRadioField());
        $registry->register(new ImageCheckboxGroupField());
        $registry->register(new HtmlField());
        $registry->register(new NoticeField());
        foreach ([
            'font_family', 'font_weight', 'unit', 'typography', 'spacing', 'dimensions',
            'background', 'gradient', 'border', 'border_radius', 'box_shadow', 'responsive',
            'button_group', 'datetime', 'post_select', 'term_select', 'user_select',
            'menu_select', 'sidebar_select', 'template_select', 'key_value', 'hidden',
            'password', 'json', 'css', 'javascript', 'heading', 'divider',
        ] as $advancedType) {
            $registry->register(new AdvancedField($advancedType));
        }

        do_action('pressundo_framework_register_fields', $registry);
    }
}
