<?php

namespace OTGH\AccessControl\Core\Models\Access;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OTGH\AccessControl\Core\Models\BaseModel;

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
