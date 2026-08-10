<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use OTGH\AccessControl\Core\Models\Hardware\Source;

interface SourceConnectionTesterInterface
{
    /**
     * @return array<int,string>
     */
    public function supportedSourceTypes(): array;

    public function test(Source $source): string;
}
