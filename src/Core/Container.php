<?php

declare(strict_types=1);

namespace Pressundo\Framework\Core;

use InvalidArgumentException;

final class Container
{
    /** @var array<class-string, callable(self):object|callable():object> */
    private array $factories = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @param class-string $id */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** @template T of object @param class-string<T> $id @return T */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (! isset($this->factories[$id])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal and are not rendered as HTML.
            throw new InvalidArgumentException(sprintf('Service "%s" is not registered.', $id));
        }

        $factory = $this->factories[$id];
        $reflection = new \ReflectionFunction(\Closure::fromCallable($factory));
        $instance = $reflection->getNumberOfParameters() > 0 ? $factory($this) : $factory();

        if (! is_object($instance)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal and are not rendered as HTML.
            throw new InvalidArgumentException(sprintf('Service "%s" must resolve to an object.', $id));
        }

        return $this->instances[$id] = $instance;
    }
}
