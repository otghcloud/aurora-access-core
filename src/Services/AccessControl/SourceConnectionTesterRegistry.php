<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

class SourceConnectionTesterRegistry
{
    /**
     * @var array<string,SourceConnectionTesterInterface>
     */
    private array $testers = [];

    public function __construct(private readonly AccessControlCapabilityRegistry $capabilities) {}

    public function register(SourceConnectionTesterInterface $tester): void
    {
        foreach ($tester->supportedSourceTypes() as $sourceType) {
            $normalized = $this->capabilities->normalizeSourceType($sourceType) ?? strtolower(trim($sourceType));

            if ($normalized === '') {
                continue;
            }

            $this->testers[$normalized] = $tester;
        }
    }

    public function resolve(string $sourceType): ?SourceConnectionTesterInterface
    {
        $normalized = $this->capabilities->normalizeSourceType($sourceType) ?? strtolower(trim($sourceType));

        return $this->testers[$normalized] ?? null;
    }
}
