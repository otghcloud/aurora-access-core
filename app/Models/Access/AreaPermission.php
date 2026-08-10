<?php

namespace App\Models\Access;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaPermission extends BaseModel
{
    protected $table = 'area_permissions';

    protected $fillable = [
        'individual_id',
        'area_id',
        'permission',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function accessUser(): BelongsTo
    {
        return $this->belongsTo(Individual::class, 'individual_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function room(): BelongsTo
    {
        return $this->area();
    }
}
