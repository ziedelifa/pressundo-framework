<?php

declare(strict_types=1);

namespace Pressundo\Framework\Api;

use Pressundo\Framework\Migration\Progressive\ProgressiveMigrationService;

final class Factory
{
    public function __construct(private readonly ProgressiveMigrationService $migration)
    {
    }

    public function options(string $name, array $config = []): ValueNamespace
    {
        $config['storage'] = 'option';
        $config['storage_key'] ??= $name;
        return new ValueNamespace($this->migration, $name, $config);
    }

    public function meta(int $postId, string $name, array $config = []): ValueNamespace
    {
        $config['storage'] = 'post_meta';
        $config['post_id'] = $postId;
        $config['storage_key'] ??= $name;
        return new ValueNamespace($this->migration, $name, $config);
    }
}
