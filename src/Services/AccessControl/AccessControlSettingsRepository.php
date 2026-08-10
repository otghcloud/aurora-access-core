<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use OTGH\AccessControl\Core\Models\Setting;

class AccessControlSettingsRepository
{
    private const CACHE_PREFIX = 'access_control.settings.';

    public function get(string $key, mixed $default = null): mixed
    {
        $normalized = $this->normalizeKey($key);

        if ($normalized === '') {
            return $default;
        }

        $cacheKey = self::CACHE_PREFIX.$normalized;

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($normalized, $default) {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            $setting = Setting::query()->where('key', $normalized)->first();
            if (! $setting instanceof Setting) {
                return $default;
            }

            $value = $setting->getAttribute('value');
            if ($value === null) {
                return $default;
            }

            return $value;
        });
    }

    public function set(string $key, mixed $value): void
    {
        $normalized = $this->normalizeKey($key);

        if ($normalized === '' || ! Schema::hasTable('settings')) {
            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => $normalized],
            ['value' => $value]
        );

        Cache::forget(self::CACHE_PREFIX.$normalized);
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function normalizeKey(string $key): string
    {
        return trim(str_replace('..', '.', $key), '. ');
    }
}
