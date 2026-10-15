<?php

declare(strict_types=1);

use Pressundo\Framework\Api\Factory;
use Pressundo\Framework\Api\ValueNamespace;
use Pressundo\Framework\Bootstrap;
use Pressundo\Framework\Migration\Progressive\ProgressiveMigrationService;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API intentionally uses the stable pu_* prefix.

if (! function_exists('pu_get')) {
    function pu_get(string $key, mixed $default = null, array $args = []): mixed
    {
        /** @var ProgressiveMigrationService $service */
        $service = Bootstrap::instance()->container()->get(ProgressiveMigrationService::class);
        return $service->get($key, $default, $args);
    }
}

if (! function_exists('pu_set')) {
    function pu_set(string $key, mixed $value, array $args = []): bool
    {
        /** @var ProgressiveMigrationService $service */
        $service = Bootstrap::instance()->container()->get(ProgressiveMigrationService::class);
        return $service->set($key, $value, $args);
    }
}

if (! function_exists('pu_delete')) {
    function pu_delete(string $key, array $args = []): bool
    {
        /** @var ProgressiveMigrationService $service */
        $service = Bootstrap::instance()->container()->get(ProgressiveMigrationService::class);
        return $service->delete($key, $args);
    }
}

if (! function_exists('pu_exists')) {
    function pu_exists(string $key, array $args = []): bool
    {
        /** @var ProgressiveMigrationService $service */
        $service = Bootstrap::instance()->container()->get(ProgressiveMigrationService::class);
        return $service->exists($key, $args);
    }
}

if (! function_exists('pu_options')) {
    function pu_options(string $name, array $config = []): ValueNamespace
    {
        /** @var Factory $factory */
        $factory = Bootstrap::instance()->container()->get(Factory::class);
        return $factory->options($name, $config);
    }
}

if (! function_exists('pu_meta')) {
    function pu_meta(int $postId, string $name, array $config = []): ValueNamespace
    {
        /** @var Factory $factory */
        $factory = Bootstrap::instance()->container()->get(Factory::class);
        return $factory->meta($postId, $name, $config);
    }
}

if (! function_exists('pu_api_version')) {
    function pu_api_version(): string
    {
        return defined('PRESSUNDO_FRAMEWORK_API_VERSION') ? (string) PRESSUNDO_FRAMEWORK_API_VERSION : '1';
    }
}

if (! function_exists('pu_field')) {
    /** @return array<string,mixed> */
    function pu_field(string $type, string $id, array $args = []): array
    {
        /** @var \Pressundo\Framework\Api\FieldFactory $factory */
        $factory = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\FieldFactory::class);
        return $factory->make($type, $id, $args);
    }
}

if (! function_exists('pu_text')) {
    /** @return array<string,mixed> */
    function pu_text(string $id, array $args = []): array
    {
        return pu_field('text', $id, $args);
    }
}

if (! function_exists('pu_textarea')) {
    /** @return array<string,mixed> */
    function pu_textarea(string $id, array $args = []): array
    {
        return pu_field('textarea', $id, $args);
    }
}

if (! function_exists('pu_select')) {
    /** @param array<string|int,mixed> $options @return array<string,mixed> */
    function pu_select(string $id, array|callable $options = [], array $args = []): array
    {
        $args['options'] = $options;
        return pu_field('select', $id, $args);
    }
}

if (! function_exists('pu_checkbox')) {
    /** @return array<string,mixed> */
    function pu_checkbox(string $id, array $args = []): array
    {
        return pu_field('checkbox', $id, $args);
    }
}

if (! function_exists('pu_toggle')) {
    /** @return array<string,mixed> */
    function pu_toggle(string $id, array $args = []): array
    {
        return pu_field('toggle', $id, $args);
    }
}

if (! function_exists('pu_group')) {
    /** @param list<array<string,mixed>> $fields @return array<string,mixed> */
    function pu_group(string $id, array $fields, array $args = []): array
    {
        /** @var \Pressundo\Framework\Api\FieldFactory $factory */
        $factory = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\FieldFactory::class);
        return $factory->group($id, $fields, $args);
    }
}

if (! function_exists('pu_repeater')) {
    /** @param list<array<string,mixed>> $fields @return array<string,mixed> */
    function pu_repeater(string $id, array $fields, array $args = []): array
    {
        /** @var \Pressundo\Framework\Api\FieldFactory $factory */
        $factory = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\FieldFactory::class);
        return $factory->repeater($id, $fields, $args);
    }
}

if (! function_exists('pu_section')) {
    /** @param list<array<string,mixed>> $fields @return array<string,mixed> */
    function pu_section(string $id, array $fields, array $args = []): array
    {
        /** @var \Pressundo\Framework\Api\FieldFactory $factory */
        $factory = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\FieldFactory::class);
        return $factory->section($id, $fields, $args);
    }
}

if (! function_exists('pu_register_field_type')) {
    function pu_register_field_type(\Pressundo\Framework\Contracts\FieldTypeInterface $field): void
    {
        /** @var \Pressundo\Framework\Registry\FieldRegistry $registry */
        $registry = Bootstrap::instance()->container()->get(\Pressundo\Framework\Registry\FieldRegistry::class);
        $registry->register($field);
        if (function_exists('do_action')) {
            do_action('pressundo_field_type_registered', $field, $registry);
        }
    }
}

if (! function_exists('pu_has_field_type')) {
    function pu_has_field_type(string $type): bool
    {
        /** @var \Pressundo\Framework\Registry\FieldRegistry $registry */
        $registry = Bootstrap::instance()->container()->get(\Pressundo\Framework\Registry\FieldRegistry::class);
        return $registry->has($type);
    }
}

if (! function_exists('pu_field_types')) {
    /** @return list<string> */
    function pu_field_types(): array
    {
        /** @var \Pressundo\Framework\Registry\FieldRegistry $registry */
        $registry = Bootstrap::instance()->container()->get(\Pressundo\Framework\Registry\FieldRegistry::class);
        return $registry->types();
    }
}

if (! function_exists('pu_sanitize_field')) {
    /** @param array<string,mixed> $field */
    function pu_sanitize_field(array $field, mixed $value): mixed
    {
        /** @var \Pressundo\Framework\Api\FieldValueProcessor $processor */
        $processor = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\FieldValueProcessor::class);
        return $processor->sanitize($field, $value);
    }
}

if (! function_exists('pu_validate_field')) {
    /** @param array<string,mixed> $field */
    function pu_validate_field(array $field, mixed $value): \Pressundo\Framework\Api\ValidationResult
    {
        /** @var \Pressundo\Framework\Api\FieldValueProcessor $processor */
        $processor = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\FieldValueProcessor::class);
        return $processor->validate($field, $value);
    }
}

if (! function_exists('pu_number')) {
    /** @return array<string,mixed> */
    function pu_number(string $id, array $args = []): array
    {
        return pu_field('number', $id, $args);
    }
}

if (! function_exists('pu_email')) {
    /** @return array<string,mixed> */
    function pu_email(string $id, array $args = []): array
    {
        return pu_field('email', $id, $args);
    }
}

if (! function_exists('pu_url')) {
    /** @return array<string,mixed> */
    function pu_url(string $id, array $args = []): array
    {
        return pu_field('url', $id, $args);
    }
}

if (! function_exists('pu_radio')) {
    /** @param array<string|int,mixed>|callable $options @return array<string,mixed> */
    function pu_radio(string $id, array|callable $options = [], array $args = []): array
    {
        $args['options'] = $options;
        return pu_field('radio', $id, $args);
    }
}

if (! function_exists('pu_multiselect')) {
    /** @param array<string|int,mixed>|callable $options @return array<string,mixed> */
    function pu_multiselect(string $id, array|callable $options = [], array $args = []): array
    {
        $args['options'] = $options;
        return pu_field('multi_select', $id, $args);
    }
}

if (! function_exists('pu_checkbox_group')) {
    /** @param array<string|int,mixed>|callable $options @return array<string,mixed> */
    function pu_checkbox_group(string $id, array|callable $options = [], array $args = []): array
    {
        $args['options'] = $options;
        return pu_field('checkbox_group', $id, $args);
    }
}

if (! function_exists('pu_color')) {
    /** @return array<string,mixed> */
    function pu_color(string $id, array $args = []): array
    {
        return pu_field('color', $id, $args);
    }
}

if (! function_exists('pu_date')) {
    /** @return array<string,mixed> */
    function pu_date(string $id, array $args = []): array
    {
        return pu_field('date', $id, $args);
    }
}

if (! function_exists('pu_time')) {
    /** @return array<string,mixed> */
    function pu_time(string $id, array $args = []): array
    {
        return pu_field('time', $id, $args);
    }
}

if (! function_exists('pu_range')) {
    /** @return array<string,mixed> */
    function pu_range(string $id, array $args = []): array
    {
        return pu_field('range', $id, $args);
    }
}

if (! function_exists('pu_media')) {
    /** @return array<string,mixed> */
    function pu_media(string $id, array $args = []): array
    {
        return pu_field('media', $id, $args);
    }
}

if (! function_exists('pu_gallery')) {
    /** @return array<string,mixed> */
    function pu_gallery(string $id, array $args = []): array
    {
        return pu_field('gallery', $id, $args);
    }
}

if (! function_exists('pu_editor')) {
    /** @return array<string,mixed> */
    function pu_editor(string $id, array $args = []): array
    {
        return pu_field('editor', $id, $args);
    }
}

if (! function_exists('pu_code')) {
    /** @return array<string,mixed> */
    function pu_code(string $id, array $args = []): array
    {
        return pu_field('code', $id, $args);
    }
}

if (! function_exists('pu_icon')) {
    /** @return array<string,mixed> */
    function pu_icon(string $id, array $args = []): array
    {
        return pu_field('icon', $id, $args);
    }
}

if (! function_exists('pu_sortable')) {
    /** @param array<string|int,mixed>|callable $options @return array<string,mixed> */
    function pu_sortable(string $id, array|callable $options = [], array $args = []): array
    {
        $args['options'] = $options;
        return pu_field('sortable_select', $id, $args);
    }
}

if (! function_exists('pu_resolve_options')) {
    /** @param array<string,mixed> $field @return array<string,mixed> */
    function pu_resolve_options(array $field): array
    {
        /** @var \Pressundo\Framework\Api\OptionsResolver $resolver */
        $resolver = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\OptionsResolver::class);
        return $resolver->resolve($field);
    }
}

if (! function_exists('pu_field_visible')) {
    /** @param array<string,mixed> $field @param array<string,mixed> $values */
    function pu_field_visible(array $field, array $values): bool
    {
        /** @var \Pressundo\Framework\Api\ConditionEvaluator $evaluator */
        $evaluator = Bootstrap::instance()->container()->get(\Pressundo\Framework\Api\ConditionEvaluator::class);
        return $evaluator->visible($field, $values);
    }
}

if (! function_exists('pu_options_page')) {
    /** @param array<string,mixed> $config */
    function pu_options_page(array $config): \Pressundo\Framework\Admin\OptionsPage
    {
        $container = Bootstrap::instance()->container();
        return new \Pressundo\Framework\Admin\OptionsPage(
            $config,
            $container->get(\Pressundo\Framework\Admin\AssetManager::class),
            $container->get(\Pressundo\Framework\Renderer\SchemaFormRenderer::class),
            $container->get(\Pressundo\Framework\Api\FieldValueProcessor::class)
        );
    }
}

if (! function_exists('pu_metabox')) {
    /** @param array<string,mixed> $config */
    function pu_metabox(array $config): \Pressundo\Framework\Admin\Metabox
    {
        $container = Bootstrap::instance()->container();
        return new \Pressundo\Framework\Admin\Metabox(
            $config,
            $container->get(\Pressundo\Framework\Admin\AssetManager::class),
            $container->get(\Pressundo\Framework\Renderer\SchemaFormRenderer::class),
            $container->get(\Pressundo\Framework\Api\FieldValueProcessor::class)
        );
    }
}

/* 0.13.3 Advanced Theme Fields — additive Public API v1 helpers. */
if (! function_exists('pu_font_family')) {
    function pu_font_family(string $id, array $args = []): array { return pu_field('font_family', $id, $args); }
}
if (! function_exists('pu_font_weight')) {
    function pu_font_weight(string $id, array $args = []): array { return pu_field('font_weight', $id, $args); }
}
if (! function_exists('pu_unit')) {
    function pu_unit(string $id, array $args = []): array { return pu_field('unit', $id, $args); }
}
if (! function_exists('pu_typography')) {
    function pu_typography(string $id, array $args = []): array { return pu_field('typography', $id, $args); }
}
if (! function_exists('pu_spacing')) {
    function pu_spacing(string $id, array $args = []): array { return pu_field('spacing', $id, $args); }
}
if (! function_exists('pu_dimensions')) {
    function pu_dimensions(string $id, array $args = []): array { return pu_field('dimensions', $id, $args); }
}
if (! function_exists('pu_background')) {
    function pu_background(string $id, array $args = []): array { return pu_field('background', $id, $args); }
}
if (! function_exists('pu_gradient')) {
    function pu_gradient(string $id, array $args = []): array { return pu_field('gradient', $id, $args); }
}
if (! function_exists('pu_border')) {
    function pu_border(string $id, array $args = []): array { return pu_field('border', $id, $args); }
}
if (! function_exists('pu_border_radius')) {
    function pu_border_radius(string $id, array $args = []): array { return pu_field('border_radius', $id, $args); }
}
if (! function_exists('pu_box_shadow')) {
    function pu_box_shadow(string $id, array $args = []): array { return pu_field('box_shadow', $id, $args); }
}
if (! function_exists('pu_responsive')) {
    function pu_responsive(string $id, array $args = []): array { return pu_field('responsive', $id, $args); }
}
if (! function_exists('pu_button_group')) {
    /** @param array<string|int,mixed> $options */
    function pu_button_group(string $id, array $options = [], array $args = []): array { $args['options'] = $options; return pu_field('button_group', $id, $args); }
}
if (! function_exists('pu_datetime')) {
    function pu_datetime(string $id, array $args = []): array { return pu_field('datetime', $id, $args); }
}
if (! function_exists('pu_post_select')) {
    function pu_post_select(string $id, array $args = []): array {
        if (! isset($args['options']) && ! isset($args['options_callback'])) {
            $postTypes = $args['post_type'] ?? ['post', 'page'];
            $postTypes = is_array($postTypes) ? $postTypes : [$postTypes];
            $args['options_callback'] = static function () use ($postTypes, $args): array {
                $items = [];
                $posts = get_posts(['post_type'=>$postTypes,'post_status'=>['publish','draft','private'],'posts_per_page'=>(int)($args['limit'] ?? 100),'orderby'=>'title','order'=>'ASC']);
                foreach ($posts as $post) { $items[] = ['value'=>(string)$post->ID,'label'=>$post->post_title !== '' ? $post->post_title : ('#' . $post->ID)]; }
                return $items;
            };
        }
        $args['searchable'] = $args['searchable'] ?? true;
        return pu_field('post_select', $id, $args);
    }
}
if (! function_exists('pu_term_select')) {
    function pu_term_select(string $id, array $args = []): array {
        if (! isset($args['options']) && ! isset($args['options_callback'])) {
            $taxonomy = (string) ($args['taxonomy'] ?? 'category');
            $args['options_callback'] = static function () use ($taxonomy): array {
                $items = []; $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false]);
                if (! is_wp_error($terms)) { foreach ($terms as $term) { $items[] = ['value'=>(string)$term->term_id,'label'=>$term->name]; } }
                return $items;
            };
        }
        $args['searchable'] = $args['searchable'] ?? true;
        return pu_field('term_select', $id, $args);
    }
}
if (! function_exists('pu_user_select')) {
    function pu_user_select(string $id, array $args = []): array {
        if (! isset($args['options']) && ! isset($args['options_callback'])) {
            $args['options_callback'] = static function () use ($args): array {
                $items = []; foreach (get_users(['number'=>(int)($args['limit'] ?? 100),'orderby'=>'display_name']) as $user) { $items[] = ['value'=>(string)$user->ID,'label'=>$user->display_name . ' — ' . $user->user_email]; } return $items;
            };
        }
        $args['searchable'] = $args['searchable'] ?? true;
        return pu_field('user_select', $id, $args);
    }
}
if (! function_exists('pu_menu_select')) {
    function pu_menu_select(string $id, array $args = []): array {
        if (! isset($args['options']) && ! isset($args['options_callback'])) { $args['options_callback'] = static function (): array { $items=[]; foreach (wp_get_nav_menus() as $menu) { $items[]=['value'=>(string)$menu->term_id,'label'=>$menu->name]; } return $items; }; }
        return pu_field('menu_select', $id, $args);
    }
}
if (! function_exists('pu_sidebar_select')) {
    function pu_sidebar_select(string $id, array $args = []): array {
        if (! isset($args['options']) && ! isset($args['options_callback'])) { $args['options_callback'] = static function (): array { global $wp_registered_sidebars; $items=[]; foreach ((array)$wp_registered_sidebars as $key=>$sidebar) { $items[(string)$key]=(string)($sidebar['name'] ?? $key); } return $items; }; }
        return pu_field('sidebar_select', $id, $args);
    }
}
if (! function_exists('pu_template_select')) {
    function pu_template_select(string $id, array $args = []): array {
        if (! isset($args['options']) && ! isset($args['options_callback'])) { $args['options_callback'] = static function (): array { $items=['default'=>__('Default template','pressundo-framework')]; foreach (wp_get_theme()->get_page_templates() as $name=>$file) { $items[(string)$file]=(string)$name; } return $items; }; }
        return pu_field('template_select', $id, $args);
    }
}
if (! function_exists('pu_key_value')) {
    function pu_key_value(string $id, array $args = []): array { return pu_field('key_value', $id, $args); }
}
if (! function_exists('pu_hidden')) {
    function pu_hidden(string $id, array $args = []): array { return pu_field('hidden', $id, $args); }
}
if (! function_exists('pu_password')) {
    function pu_password(string $id, array $args = []): array { return pu_field('password', $id, $args); }
}
if (! function_exists('pu_json')) {
    function pu_json(string $id, array $args = []): array { return pu_field('json', $id, $args); }
}
if (! function_exists('pu_css')) {
    function pu_css(string $id, array $args = []): array { return pu_field('css', $id, $args); }
}
if (! function_exists('pu_javascript')) {
    function pu_javascript(string $id, array $args = []): array { return pu_field('javascript', $id, $args); }
}
if (! function_exists('pu_heading')) {
    function pu_heading(string $id, string $text, array $args = []): array { $args['text'] = $text; return pu_field('heading', $id, $args); }
}
if (! function_exists('pu_divider')) {
    function pu_divider(string $id, array $args = []): array { return pu_field('divider', $id, $args); }
}
