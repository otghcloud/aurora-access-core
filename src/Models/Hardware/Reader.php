<?php

namespace OTGH\AccessControl\Core\Models\Hardware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\BaseModel;

class Reader extends BaseModel
{
    protected $fillable = [
        'name',
        'identifier',
        'area_id',
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

    protected static function booted(): void
    {
        static::saving(function (self $reader): void {
            if ($reader->area_id === null) {
                $reader->area_id = Area::ensureUnassignedArea()->id;
            }
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'origin_id')
            ->where('origin_type', 'reader');
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
            ->where('target_type', 'reader');
    }

    public function lockBindings(): HasMany
    {
        return $this->hasMany(ReaderLockBinding::class, 'reader_id');
    }

    public function targetLocks(): HasManyThrough
    {
        return $this->hasManyThrough(
            Lock::class,
            ReaderLockBinding::class,
            'reader_id',
            'id',
            'id',
            'lock_id'
        );
    }
}
