<?php

namespace App\Services\AccessControl;

class AccessControlConfigurationRegistry
{
    /**
     * @var array<string,array{key:string,label:string,type:string,description:string,section:string,section_label:string,package:?string,default:mixed}>
     */
    private array $fields = [];

    public function registerField(
        string $key,
        string $label,
        string $type = 'string',
        string $description = '',
        string $section = 'core',
        string $sectionLabel = 'Core',
        ?string $package = null,
        mixed $default = null,
    ): void {
        $normalizedKey = trim($key);

        if ($normalizedKey === '') {
            return;
        }

        $this->fields[$normalizedKey] = [
            'key' => $normalizedKey,
            'label' => trim($label) !== '' ? trim($label) : $normalizedKey,
            'type' => $this->normalizeType($type),
            'description' => trim($description),
            'section' => trim($section) !== '' ? trim($section) : 'core',
            'section_label' => trim($sectionLabel) !== '' ? trim($sectionLabel) : 'Core',
            'package' => $package !== null && trim($package) !== '' ? trim($package) : null,
            'default' => $default,
        ];
    }

    /**
     * @return array<int,array{key:string,label:string,type:string,description:string,section:string,section_label:string,package:?string,default:mixed}>
     */
    public function allFields(): array
    {
        $fields = array_values($this->fields);

        usort($fields, static function (array $a, array $b): int {
            $sectionCompare = strcmp($a['section_label'], $b['section_label']);
            if ($sectionCompare !== 0) {
                return $sectionCompare;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $fields;
    }

    /**
     * @return array<int,array{section:string,section_label:string,package:?string,fields:array<int,array{key:string,label:string,type:string,description:string,section:string,section_label:string,package:?string,default:mixed}>}>
     */
    public function groupedFields(): array
    {
        $grouped = [];

        foreach ($this->allFields() as $field) {
            $section = $field['section'];

            if (! isset($grouped[$section])) {
                $grouped[$section] = [
                    'section' => $section,
                    'section_label' => $field['section_label'],
                    'package' => $field['package'],
                    'fields' => [],
                ];
            }

            if ($grouped[$section]['package'] === null && $field['package'] !== null) {
                $grouped[$section]['package'] = $field['package'];
            }

            $grouped[$section]['fields'][] = $field;
        }

        return array_values($grouped);
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'int' => 'integer',
            'bool' => 'boolean',
            'array' => 'json',
            default => in_array($normalized, ['string', 'integer', 'float', 'boolean', 'json'], true)
                ? $normalized
                : 'string',
        };
    }
}
