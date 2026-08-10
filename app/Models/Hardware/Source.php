<?php

namespace App\Models\Hardware;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends BaseModel
{
    protected $fillable = [
        'name',
        'identifier',
        'type',
        'endpoint',
        'enabled',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'enabled' => 'boolean',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function adapterBindings(): HasMany
    {
        return $this->hasMany(AdapterBinding::class, 'source_id');
    }
}
