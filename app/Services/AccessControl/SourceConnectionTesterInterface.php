<?php

namespace App\Services\AccessControl;

use App\Models\Hardware\Source;

interface SourceConnectionTesterInterface
{
    /**
     * @return array<int,string>
     */
    public function supportedSourceTypes(): array;

    public function test(Source $source): string;
}
