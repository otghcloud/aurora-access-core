<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

class NullSerialReaderDiagnosticsService implements SerialReaderDiagnosticsServiceInterface
{
    /**
     * @return array<string,mixed>
     */
    public function buildPayload(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'readers_total' => 0,
            'running_monitors' => 0,
            'readable_devices' => 0,
            'command_processes' => 0,
            'readers' => [],
            'adapter_available' => false,
        ];
    }
}
