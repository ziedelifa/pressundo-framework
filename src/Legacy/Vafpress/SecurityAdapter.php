<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

/**
 * Compatibility-only facade for the public VP_Security surface.
 *
 * Pressundo intentionally does NOT recreate Vafpress's generic AJAX callback
 * execution. The whitelist is retained only so legacy declarations and
 * dependency callbacks keep their historical API contract.
 */
final class SecurityAdapter
{
    private static ?self $instance = null;

    /** @var array<string,true> */
    private array $functions = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Historical Vafpress contract: returns the function name when newly added,
     * false when it was already present.
     */
    public function whitelist_function($name): string|false
    {
        if (! is_string($name) || $name === '' || isset($this->functions[$name])) {
            return false;
        }
        $this->functions[$name] = true;
        return $name;
    }

    public function is_function_whitelisted($name): bool
    {
        return is_string($name) && isset($this->functions[$name]);
    }

    /** Pressundo extension; harmless for legacy callers. */
    public function unwhitelist_function($name): self
    {
        if (is_string($name)) { unset($this->functions[$name]); }
        return $this;
    }

    /** @return list<string> */
    public function get_whitelisted_functions(): array
    {
        return array_keys($this->functions);
    }
}
