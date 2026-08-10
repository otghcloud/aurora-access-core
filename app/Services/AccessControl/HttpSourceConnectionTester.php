<?php

namespace App\Services\AccessControl;

use App\Models\Hardware\Source;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSourceConnectionTester implements SourceConnectionTesterInterface
{
    /**
     * @return array<int,string>
     */
    public function supportedSourceTypes(): array
    {
        return ['http'];
    }

    public function test(Source $source): string
    {
        $endpoint = $this->nullableString($source->endpoint);

        if ($endpoint === null) {
            throw new RuntimeException('HTTP source has no endpoint configured.');
        }

        $response = Http::timeout(5)->get($endpoint);

        return sprintf('HTTP source test passed: status=%d.', $response->status());
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
