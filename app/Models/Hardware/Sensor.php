<?php

namespace App\Models\Hardware;

use App\Models\Access\Area;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends BaseModel
{
    protected $fillable = [
        'area_id',
        'name',
        'identifier',
        'state',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'state' => 'boolean',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function adapterBindings(): HasMany
    {
        return $this->hasMany(AdapterBinding::class, 'target_id')
            ->where('target_type', 'sensor');
    }
}
