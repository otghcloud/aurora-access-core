<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

interface SerialReaderDiagnosticsServiceInterface
{
    /**
     * @return array<string,mixed>
     */
    public function buildPayload(): array;
}
