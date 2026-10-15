<?php

declare(strict_types=1);

namespace Pressundo\Framework\Migration;

final class RuntimeCutoverPlanner
{
    /** @param array<string,mixed> $configuration @return array<string,mixed> */
    public function build(array $configuration): array
    {
        $readiness = (array) ($configuration['readiness'] ?? []);
        $ready = ($readiness['status'] ?? '') === 'ready';
        $runtimeSymbols = [
            'VP_VERSION' => defined('VP_VERSION'),
            'VP_Option' => class_exists('VP_Option', false),
            'VP_Metabox' => class_exists('VP_Metabox', false),
        ];
        $loaded = in_array(true, $runtimeSymbols, true);
        $storage = (array) ($configuration['plan']['storage'] ?? []);

        return [
            'status' => $ready ? 'candidate' : 'blocked',
            'runtime_loaded' => $loaded,
            'runtime_symbols' => $runtimeSymbols,
            'storage_type' => (string) ($storage['type'] ?? ''),
            'storage_key' => (string) ($storage['key'] ?? ''),
            'compatibility_runtime_candidate' => $ready,
            'can_disable_legacy_runtime' => false,
            'message' => $ready
                ? 'This configuration is ready for a compatibility-runtime takeover preview. 0.10 can still build and verify this advanced replacement preview, but progressive source migration does not require a runtime cutover.'
                : 'Runtime replacement remains blocked until migration readiness reaches Ready.',
            'steps' => [
                'Keep the exact legacy storage key and payload contract.',
                'Register the normalized schema through the modern Pressundo core.',
                'Mount the replacement admin renderer without removing the legacy runtime.',
                'Run dual-read verification against the same legacy storage record.',
                'Detach legacy renderer hooks only in a future explicit cutover mode.',
                'Retain rollback and a compatibility shim until production verification is complete.',
            ],
        ];
    }
}
