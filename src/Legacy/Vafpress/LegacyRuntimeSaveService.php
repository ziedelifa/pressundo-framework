<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

use Pressundo\Framework\Migration\ShadowSubmissionSimulator;

final class LegacyRuntimeSaveService
{
    public function __construct(
        private readonly ShadowSubmissionSimulator $simulator,
        private readonly LegacyDefaults $defaults
    ) {}

    /**
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $current
     * @param array<string,mixed> $submitted
     * @return array<string,mixed>
     */
    public function sanitize(array $schema, array $current, array $submitted): array
    {
        // New installations/posts do not have a legacy payload yet. Seed the
        // in-memory baseline from schema defaults so groups/repeaters can be
        // edited immediately without creating any legacy data until save.
        $baseline = array_replace($this->defaults->fromSchema($schema), $current);

        return $this->simulator->simulate([
            'resolved' => true,
            'schema' => $schema,
            'shadow_data' => [
                'sample_values' => $baseline,
                'samples' => 1,
            ],
            'shadow_save' => [
                'write_gate_passed' => true,
            ],
            'compatibility_runtime' => [
                // A compatibility save is not a migration audit. Existing values
                // that the developer did not change must survive byte-for-byte,
                // even if a dynamic datasource or legacy environment changed.
                'preserve_unchanged' => true,
            ],
        ], $submitted);
    }
}
