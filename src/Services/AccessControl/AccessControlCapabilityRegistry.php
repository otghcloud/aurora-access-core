<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use RuntimeException;

class AccessControlCapabilityRegistry
{
    /**
     * @var array<string,array{label:string,aliases:array<int,string>}>
     */
    private array $bindingAdapterTypes = [];

    /**
     * @var array<string,array{label:string,aliases:array<int,string>}>
     */
    private array $sourceTypes = [];

    /**
     * @param  array<int,string>  $aliases
     */
    public function registerBindingAdapterType(string $type, string $label, array $aliases = []): void
    {
        $canonical = $this->normalizeToken($type);

        if ($canonical === '') {
            throw new RuntimeException('Binding adapter type cannot be empty.');
        }

        $this->bindingAdapterTypes[$canonical] = [
            'label' => trim($label) !== '' ? trim($label) : strtoupper($canonical),
            'aliases' => $this->normalizeAliases($aliases, $canonical),
        ];
    }

    /**
     * @param  array<int,string>  $aliases
     */
    public function registerSourceType(string $type, string $label, array $aliases = []): void
    {
        $canonical = $this->normalizeToken($type);

        if ($canonical === '') {
            throw new RuntimeException('Source type cannot be empty.');
        }

        $this->sourceTypes[$canonical] = [
            'label' => trim($label) !== '' ? trim($label) : strtoupper($canonical),
            'aliases' => $this->normalizeAliases($aliases, $canonical),
        ];
    }

    /**
     * @return array<int,string>
     */
    public function bindingAdapterValidationValues(): array
    {
        return $this->flattenValidationValues($this->bindingAdapterTypes);
    }

    /**
     * @return array<int,string>
     */
    public function sourceTypeValidationValues(): array
    {
        return $this->flattenValidationValues($this->sourceTypes);
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function bindingAdapterOptions(): array
    {
        return $this->toOptions($this->bindingAdapterTypes);
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function sourceTypeOptions(): array
    {
        return $this->toOptions($this->sourceTypes);
    }

    public function normalizeBindingAdapterType(?string $type): ?string
    {
        return $this->normalizeFromCatalog($type, $this->bindingAdapterTypes);
    }

    public function normalizeSourceType(?string $type): ?string
    {
        return $this->normalizeFromCatalog($type, $this->sourceTypes);
    }

    /**
     * @param  array<string,array{label:string,aliases:array<int,string>}>  $catalog
     */
    private function normalizeFromCatalog(?string $value, array $catalog): ?string
    {
        $candidate = $this->normalizeToken($value);

        if ($candidate === '') {
            return null;
        }

        if (isset($catalog[$candidate])) {
            return $candidate;
        }

        foreach ($catalog as $canonical => $entry) {
            if (in_array($candidate, $entry['aliases'], true)) {
                return $canonical;
            }
        }

        return $candidate;
    }

    /**
     * @param  array<string,array{label:string,aliases:array<int,string>}>  $catalog
     * @return array<int,array{value:string,label:string}>
     */
    private function toOptions(array $catalog): array
    {
        ksort($catalog);

        $options = [];
        foreach ($catalog as $value => $entry) {
            $options[] = [
                'value' => $value,
                'label' => $entry['label'],
            ];
        }

        return $options;
    }

    /**
     * @param  array<string,array{label:string,aliases:array<int,string>}>  $catalog
     * @return array<int,string>
     */
    private function flattenValidationValues(array $catalog): array
    {
        $values = [];

        foreach ($catalog as $canonical => $entry) {
            $values[] = $canonical;

            foreach ($entry['aliases'] as $alias) {
                $values[] = $alias;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  array<int,string>  $aliases
     * @return array<int,string>
     */
    private function normalizeAliases(array $aliases, string $canonical): array
    {
        $normalized = [];

        foreach ($aliases as $alias) {
            $token = $this->normalizeToken($alias);
            if ($token === '' || $token === $canonical) {
                continue;
            }

            $normalized[] = $token;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    private function normalizeToken(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtolower(trim($value));
    }
}
