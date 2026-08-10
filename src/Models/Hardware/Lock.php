<?php

namespace OTGH\AccessControl\Core\Models\Hardware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\BaseModel;

class Lock extends BaseModel
{
    protected $fillable = [
        'area_id',
        'name',
        'identifier',
        'is_primary',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'is_primary' => 'boolean',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function room(): BelongsTo
    {
        return $this->area();
    }

    public function adapterBindings(): HasMany
    {
        return $this->hasMany(AdapterBinding::class, 'target_id')
            ->where('target_type', 'lock');
    }

    public function autolockOverrideEnabled(): ?bool
    {
        $value = data_get($this->config, 'locking.autolock_override_enabled');

        return $value === null ? null : (bool) $value;
    }

    public function autolockOverrideDuration(): ?int
    {
        $value = data_get($this->config, 'locking.autolock_override_duration');

        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    public function usesAutolockOverride(): bool
    {
        return $this->autolockOverrideEnabled() !== null || $this->autolockOverrideDuration() !== null;
    }
}
