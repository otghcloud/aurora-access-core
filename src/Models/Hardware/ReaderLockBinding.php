<?php

namespace OTGH\AccessControl\Core\Models\Hardware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\BaseModel;

class ReaderLockBinding extends BaseModel
{
    protected $table = 'reader_lock_bindings';

    protected $fillable = [
        'reader_id',
        'lock_id',
        'area_id',
        'action_type',
        'enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(Reader::class, 'reader_id');
    }

    public function lock(): BelongsTo
    {
        return $this->belongsTo(Lock::class, 'lock_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
