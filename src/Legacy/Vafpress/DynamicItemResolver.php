<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use Pressundo\Framework\Security\SecurityPolicy;

final class DynamicItemResolver
{
    public function __construct(private readonly ?MigrationOverrideRepository $overrides = null) {}
    /**
     * Resolve only explicitly allow-listed Vafpress datasource functions.
     * Arbitrary custom PHP is never executed.
     *
     * @param mixed $items
     * @return array{options:array<string,array{label:string,image:?string}>,resolved:bool,dynamic:bool,source:string,message:string}
     */
    public function resolve(mixed $items): array
    {
        if (! is_array($items) || empty($items['data']) || ! is_array($items['data'])) {
            return ['options' => [], 'resolved' => true, 'dynamic' => false, 'source' => '', 'message' => ''];
        }

        $data = (array) $items['data'];
        $descriptors = (isset($data['source']) || isset($data['value'])) ? [$data] : array_values($data);

        $options = [];
        $resolvedAny = false;
        $sources = [];
        $messages = [];
        foreach ($descriptors as $descriptor) {
            if (! is_array($descriptor)) {
                continue;
            }
            $source = strtolower((string) ($descriptor['source'] ?? ''));
            $value = sanitize_key((string) ($descriptor['value'] ?? ''));
            if ($source !== 'function') {
                return [
                    'options' => [],
                    'resolved' => false,
                    'dynamic' => true,
                    'source' => $source . ':' . $value,
                    'message' => 'Dynamic item binding is runtime-dependent and remains review-only.',
                ];
            }

            $resolverFunction = $value;
            $override = $this->overrides?->datasource($value);
            if (is_string($override) && $override !== '') {
                $resolverFunction = $override;
                $messages[] = sprintf('Explicit migration override maps datasource "%s" to "%s".', $value, $override);
            }

            $resolved = $this->resolveFunction($resolverFunction);
            if ($resolved === null) {
                $resolved = $this->resolveExplicitLegacyFunction($resolverFunction);
            }
            if ($resolved === null) {
                return [
                    'options' => [],
                    'resolved' => false,
                    'dynamic' => true,
                    'source' => 'function:' . $value,
                    'message' => sprintf('Datasource function "%s" is neither built-in nor explicitly whitelisted by the theme and was not executed.', $value),
                ];
            }

            $resolvedAny = true;
            $sources[] = 'function:' . $value;
            $options = array_replace($options, $resolved);
        }

        return [
            'options' => $options,
            'resolved' => $resolvedAny,
            'dynamic' => true,
            'source' => implode(',', $sources),
            'message' => $resolvedAny ? ($messages !== [] ? implode(' ', $messages) : 'Known Vafpress datasource was resolved through WordPress APIs without executing legacy PHP.') : '',
        ];
    }

    /** @return array<string,array{label:string,image:?string}>|null */
    private function resolveExplicitLegacyFunction(string $function): ?array
    {
        // Preserve the historical whitelist API, but never execute arbitrary
        // legacy PHP unless the site owner explicitly opts in from wp-config.php.
        if (! SecurityPolicy::allowLegacyWhitelistedCallbacks()) {
            return null;
        }
        if (! class_exists(SecurityAdapter::class, false) || ! SecurityAdapter::instance()->is_function_whitelisted($function) || ! is_callable($function)) {
            return null;
        }

        try {
            $items = call_user_func($function);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($items)) {
            return null;
        }

        $out = [];
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $value = array_key_exists('value', $item) ? $item['value'] : $key;
                $label = array_key_exists('label', $item) ? $item['label'] : $value;
                $image = isset($item['img']) && is_scalar($item['img']) ? (string) $item['img'] : null;
            } elseif (is_object($item)) {
                $value = property_exists($item, 'value') ? $item->value : $key;
                $label = property_exists($item, 'label') ? $item->label : $value;
                $image = property_exists($item, 'img') && is_scalar($item->img) ? (string) $item->img : null;
            } elseif (is_scalar($item)) {
                $value = $key;
                $label = $item;
                $image = null;
            } else {
                continue;
            }

            if (! is_scalar($value) || ! is_scalar($label)) {
                continue;
            }
            $out[(string) $value] = ['label' => (string) $label, 'image' => $image];
        }

        return $out;
    }

    /** @return array<string,array{label:string,image:?string}>|null */
    private function resolveFunction(string $function): ?array
    {
        return match ($function) {
            'vp_get_categories' => $this->categories(),
            'vp_get_users' => $this->users(),
            'vp_get_posts' => $this->posts(),
            'vp_get_pages' => $this->pages(),
            'vp_get_tags' => $this->tags(),
            'vp_get_roles' => $this->roles(),
            'vp_get_social_medias' => $this->socialNetworks(),
            'vp_get_gwf_family' => $this->googleFontFamilies(),
            'vp_get_fontawesome_icons' => $this->fontAwesomeIcons(),
            default => null,
        };
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function categories(): array
    {
        if (! function_exists('get_categories')) {
            return [];
        }
        $out = [];
        foreach ((array) get_categories(['hide_empty' => false]) as $term) {
            if (! is_object($term) || ! isset($term->term_id)) {
                continue;
            }
            $out[(string) $term->term_id] = ['label' => (string) ($term->name ?? $term->term_id), 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function users(): array
    {
        if (! function_exists('get_users')) {
            return [];
        }
        $out = [];
        foreach ((array) get_users(['fields' => ['ID', 'display_name']]) as $user) {
            if (! is_object($user) || ! isset($user->ID)) {
                continue;
            }
            $out[(string) $user->ID] = ['label' => (string) ($user->display_name ?? $user->ID), 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function posts(): array
    {
        if (! function_exists('get_posts')) {
            return [];
        }
        $out = [];
        foreach ((array) get_posts(['posts_per_page' => -1, 'post_status' => 'any']) as $post) {
            if (! is_object($post) || ! isset($post->ID)) {
                continue;
            }
            $out[(string) $post->ID] = ['label' => (string) ($post->post_title ?? $post->ID), 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function pages(): array
    {
        if (! function_exists('get_pages')) {
            return [];
        }
        $out = [];
        foreach ((array) get_pages(['post_status' => ['publish', 'private', 'draft']]) as $page) {
            if (! is_object($page) || ! isset($page->ID)) {
                continue;
            }
            $out[(string) $page->ID] = ['label' => (string) ($page->post_title ?? $page->ID), 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function tags(): array
    {
        if (! function_exists('get_tags')) {
            return [];
        }
        $out = [];
        foreach ((array) get_tags(['hide_empty' => false]) as $term) {
            if (! is_object($term) || ! isset($term->term_id)) {
                continue;
            }
            $out[(string) $term->term_id] = ['label' => (string) ($term->name ?? $term->term_id), 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function roles(): array
    {
        $roles = function_exists('get_editable_roles') ? (array) get_editable_roles() : [];
        $out = [];
        foreach ($roles as $key => $role) {
            if (! is_array($role)) {
                continue;
            }
            $out[(string) $key] = ['label' => (string) ($role['name'] ?? $key), 'image' => null];
        }
        return $out;
    }


    /** @return array<string,array{label:string,image:?string}> */
    private function googleFontFamilies(): array
    {
        $out = [];
        foreach (array_keys(LegacyDataSources::googleFonts()) as $font) {
            $out[(string) $font] = ['label' => (string) $font, 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function fontAwesomeIcons(): array
    {
        $out = [];
        foreach (LegacyDataSources::fontAwesomeIcons() as $icon) {
            $out[$icon] = ['label' => $icon, 'image' => null];
        }
        return $out;
    }

    /** @return array<string,array{label:string,image:?string}> */
    private function socialNetworks(): array
    {
        $names = [
            'blogger' => 'Blogger', 'delicious' => 'Delicious', 'deviantart' => 'DeviantArt', 'digg' => 'Digg',
            'dribbble' => 'Dribbble', 'email' => 'Email', 'facebook' => 'Facebook', 'flickr' => 'Flickr',
            'forrst' => 'Forrst', 'foursquare' => 'Foursquare', 'github' => 'GitHub', 'googleplus' => 'Google+',
            'instagram' => 'Instagram', 'lastfm' => 'Last.FM', 'linkedin' => 'LinkedIn', 'myspace' => 'Myspace',
            'pinterest' => 'Pinterest', 'reddit' => 'Reddit', 'rss' => 'RSS', 'soundcloud' => 'SoundCloud',
            'stumbleupon' => 'StumbleUpon', 'tumblr' => 'Tumblr', 'twitter' => 'Twitter', 'vimeo' => 'Vimeo',
            'wordpress' => 'WordPress', 'yahoo' => 'Yahoo!', 'youtube' => 'YouTube',
        ];
        $out = [];
        foreach ($names as $value => $label) {
            $out[$value] = ['label' => $label, 'image' => null];
        }
        return $out;
    }
}
