<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

/**
 * Small compatibility facade for the public VP_Site_GoogleWebFont API.
 *
 * It intentionally does not copy the old Vafpress loader. It only preserves
 * the public theme-author workflow and registers HTTPS Google Fonts URLs when
 * a legacy theme explicitly asks for them.
 */
final class GoogleWebFontAdapter
{
    private static ?self $instance = null;

    /** @var array<string,array{atts:list<string>,subsets:list<string>}> */
    private array $fonts = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function add($name, $weights = 'normal', $styles = 'normal', $subsets = null): void
    {
        $name = trim((string) $name);
        if ($name === '') {
            return;
        }

        $key = str_replace(' ', '+', $name);
        if (! isset($this->fonts[$key])) {
            $this->fonts[$key] = ['atts' => [], 'subsets' => ['latin']];
        }

        foreach ((array) $weights as $weight) {
            foreach ((array) $styles as $style) {
                $weight = (string) $weight;
                $style = (string) $style;
                if ($style === 'normal') {
                    $style = '';
                }
                if ($style !== '' && $weight === 'normal') {
                    $weight = '';
                }
                if ($style === '' && ($weight === '' || $weight === 'normal')) {
                    continue;
                }
                $couple = $weight . $style;
                if ($couple !== '' && ! in_array($couple, $this->fonts[$key]['atts'], true)) {
                    $this->fonts[$key]['atts'][] = $couple;
                }
            }
        }

        if ($subsets !== null && $subsets !== '' && $subsets !== []) {
            $merged = array_merge($this->fonts[$key]['subsets'], array_map('strval', (array) $subsets));
            $this->fonts[$key]['subsets'] = array_values(array_unique(array_filter($merged, static fn (string $v): bool => $v !== '')));
        }
    }

    public function register(): void
    {
        if (! function_exists('wp_register_style')) {
            return;
        }
        foreach ($this->get_font_links() as $name => $url) {
            wp_register_style($name, $url, [], null); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        }
    }

    public function enqueue(): void
    {
        if (! function_exists('wp_enqueue_style')) {
            return;
        }
        foreach ($this->get_names() as $name) {
            wp_enqueue_style($name);
        }
    }

    public function register_and_enqueue(): void
    {
        $this->register();
        $this->enqueue();
    }

    /** @return array<string,string> */
    public function get_font_links(): array
    {
        $links = [];
        foreach ($this->fonts as $name => $font) {
            $url = 'https://fonts.googleapis.com/css?family=' . rawurlencode(str_replace('+', ' ', $name));
            if ($font['atts'] !== []) {
                $url .= ':' . implode(',', array_map('rawurlencode', $font['atts']));
            }
            if ($font['subsets'] !== []) {
                $url .= '&subset=' . implode(',', array_map('rawurlencode', $font['subsets']));
            }
            $links[$name] = $url;
        }
        return $links;
    }

    /** @return array<string,array{atts:list<string>,subsets:list<string>}> */
    public function get_fonts(): array
    {
        return $this->fonts;
    }

    /** @return list<string> */
    public function get_names(): array
    {
        return array_keys($this->fonts);
    }
}
