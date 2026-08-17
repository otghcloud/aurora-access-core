<?php

namespace OTGH\AccessControl\Core\Models\Access;

use Illuminate\Database\Eloquent\Relations\HasMany;
use OTGH\AccessControl\Core\Models\BaseModel;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\PhysicalSwitch;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;

class Area extends BaseModel
{
    public const UNASSIGNED_IDENTIFIER = 'unassigned';

    protected $table = 'areas';

    protected $fillable = [
        'name',
        'identifier',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function autolockEnabled(): bool
    {
        return (bool) data_get($this->config, 'locking.autolock_enabled', false);
    }

    public function autolockDuration(): int
    {
        return max(0, (int) data_get($this->config, 'locking.autolock_duration', 0));
    }

    public function readers(): HasMany
    {
        return $this->hasMany(Reader::class, 'area_id');
    }

    public function locks(): HasMany
    {
        return $this->hasMany(Lock::class, 'area_id');
    }

    public function switches(): HasMany
    {
        return $this->hasMany(PhysicalSwitch::class, 'area_id');
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class, 'area_id');
    }

    public function lights(): HasMany
    {
        return $this->hasMany(Light::class, 'area_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AreaPermission::class, 'area_id');
    }

    public function adapterBindings(): HasMany
    {
        return $this->hasMany(AdapterBinding::class, 'target_id')
            ->where('target_type', 'area');
    }

    public function primaryLock(): ?Lock
    {
        return $this->locks()->where('is_primary', true)->first();
    }

    public static function ensureUnassignedArea(): self
    {
        return self::query()->firstOrCreate(
            ['identifier' => self::UNASSIGNED_IDENTIFIER],
            [
                'name' => 'Unassigned',
                'config' => [
                    'locking' => [
                        'autolock_enabled' => false,
                        'autolock_duration' => 0,
                    ],
                ],
                'metadata' => ['system' => ['purpose' => 'reader_fallback_assignment']],
            ]
        );
    }
}
