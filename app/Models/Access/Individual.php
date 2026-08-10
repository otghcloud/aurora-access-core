<?php

namespace App\Models\Access;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Individual extends BaseModel
{
    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    public function areaPermissions(): HasMany
    {
        return $this->hasMany(AreaPermission::class, 'individual_id');
    }

    public function roomPermissions(): HasMany
    {
        return $this->areaPermissions();
    }
}
