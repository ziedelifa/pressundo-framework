<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class MigrationReadinessEvaluator
{
    /** @param array<string,mixed> $configuration @return array{status:string,label:string,reasons:list<string>} */
    public function evaluate(array $configuration): array
    {
        $report = (array) ($configuration['report'] ?? []);
        $plan = (array) ($configuration['plan'] ?? []);
        $shadow = (array) ($configuration['shadow_data'] ?? []);
        $roundTrip = (array) ($configuration['shadow_save'] ?? []);
        $reasons = [];

        if (empty($configuration['resolved'])) {
            $reasons[] = 'Legacy schema is not safely resolved.';
        }
        if ((int) ($report['manual'] ?? 0) > 0) {
            $reasons[] = sprintf('%d manual field(s) still require a converter.', (int) $report['manual']);
        }
        if ((array) ($configuration['warnings'] ?? []) !== []) {
            $reasons[] = 'Safe-parser warnings still require review.';
        }
        if (($shadow['status'] ?? '') === 'blocked' || (int) ($shadow['conflicts'] ?? 0) > 0) {
            $reasons[] = 'Legacy stored data contains unresolved conflicts.';
        }
        if (($roundTrip['status'] ?? '') === 'failed' || (int) ($roundTrip['storage_differences'] ?? 0) > 0) {
            $reasons[] = 'No-change storage round-trip is not identical.';
        }

        if ($reasons !== []) {
            return ['status' => 'blocked', 'label' => 'Blocked', 'reasons' => $reasons];
        }

        if ((int) ($report['review'] ?? 0) > 0) {
            $reasons[] = sprintf('%d review field(s) remain locked until their migration behavior is confirmed.', (int) $report['review']);
        }
        if ((int) ($shadow['samples'] ?? 0) < 1) {
            $reasons[] = 'No real legacy storage sample is available yet.';
        }
        if (empty($roundTrip['write_gate_passed'])) {
            $reasons[] = 'Storage round-trip gate has not passed yet.';
        }
        if (empty($plan['ready_for_write_migration'])) {
            $reasons[] = 'Controlled-write prerequisites are not complete for this storage mode.';
        }

        if ($reasons !== []) {
            return ['status' => 'needs_review', 'label' => 'Needs review', 'reasons' => array_values(array_unique($reasons))];
        }

        return [
            'status' => 'ready',
            'label' => 'Ready',
            'reasons' => ['Schema, sampled data, storage round-trip and controlled-write prerequisites passed.'],
        ];
    }
}
