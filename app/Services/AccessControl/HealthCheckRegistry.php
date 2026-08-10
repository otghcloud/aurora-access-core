<?php

namespace App\Services\AccessControl;

use App\Services\AccessControlHealthService;

class HealthCheckRegistry
{
    /**
     * @var array<int,callable(AccessControlHealthService,?string):array<int,array{name:string,status:string,details:string}>>
     */
    private array $checks = [];

    /**
     * @param  callable(AccessControlHealthService,?string):array<int,array{name:string,status:string,details:string}>  $check
     */
    public function register(callable $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @return array<int,array{name:string,status:string,details:string}>
     */
    public function runAll(AccessControlHealthService $service, ?string $readerIdentifier = null): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            $generated = $check($service, $readerIdentifier);

            if (! is_array($generated)) {
                continue;
            }

            foreach ($generated as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = trim((string) ($item['name'] ?? ''));
                $status = strtoupper(trim((string) ($item['status'] ?? 'WARN')));
                $details = trim((string) ($item['details'] ?? ''));

                if ($name === '') {
                    continue;
                }

                if (! in_array($status, ['PASS', 'WARN', 'FAIL'], true)) {
                    $status = 'WARN';
                }

                $results[] = [
                    'name' => $name,
                    'status' => $status,
                    'details' => $details,
                ];
            }
        }

        return $results;
    }
}
