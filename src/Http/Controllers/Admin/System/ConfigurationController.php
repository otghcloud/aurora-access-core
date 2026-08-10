<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\System;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlConfigurationRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;

class ConfigurationController extends Controller
{
    public function __invoke(
        AccessControlConfigurationRegistry $registry,
        AccessControlSettingsRepository $settings,
    ): View {
        $sections = array_map(function (array $section) use ($settings): array {
            $fields = array_map(function (array $field) use ($settings): array {
                $field['value'] = $settings->get($field['key'], $field['default']);

                return $field;
            }, $section['fields']);

            $section['fields'] = $fields;

            return $section;
        }, $registry->groupedFields());

        return view('admin.system.configuration', [
            'sections' => $sections,
        ]);
    }

    public function update(
        Request $request,
        AccessControlConfigurationRegistry $registry,
        AccessControlSettingsRepository $settings,
    ): RedirectResponse {
        $inputs = (array) $request->input('settings', []);
        $fieldsByKey = [];

        foreach ($registry->allFields() as $field) {
            $fieldsByKey[$field['key']] = $field;
        }

        $errors = [];

        foreach ($fieldsByKey as $key => $field) {
            if (! array_key_exists($key, $inputs)) {
                continue;
            }

            try {
                $value = $this->coerceValue($inputs[$key], $field['type']);
                $settings->set($key, $value);
            } catch (\RuntimeException $e) {
                $errors['settings.'.$key] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return redirect()->route('admin.system.configuration')->with('status', 'Configuration updated successfully.');
    }

    private function coerceValue(mixed $rawValue, string $type): mixed
    {
        return match ($type) {
            'boolean' => $this->toBool($rawValue),
            'integer' => $this->toInt($rawValue),
            'float' => $this->toFloat($rawValue),
            'json' => $this->toJson($rawValue),
            default => $this->toString($rawValue),
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function toInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new \RuntimeException('Must be a valid integer value.');
        }

        return (int) $value;
    }

    private function toFloat(mixed $value): float
    {
        if (! is_numeric($value)) {
            throw new \RuntimeException('Must be a valid numeric value.');
        }

        return (float) $value;
    }

    /**
     * @return array<int|string,mixed>
     */
    private function toJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $json = trim((string) $value);

        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Must be valid JSON object/array syntax.');
        }

        return $decoded;
    }

    private function toString(mixed $value): string
    {
        return trim((string) $value);
    }
}
