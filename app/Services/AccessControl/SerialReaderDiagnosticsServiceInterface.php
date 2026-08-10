<?php

namespace App\Services\AccessControl;

interface SerialReaderDiagnosticsServiceInterface
{
    /**
     * @return array<string,mixed>
     */
    public function buildPayload(): array;
}
