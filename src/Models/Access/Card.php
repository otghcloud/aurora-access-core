<?php

namespace OTGH\AccessControl\Core\Models\Access;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OTGH\AccessControl\Core\Models\BaseModel;

class Card extends BaseModel
{
    protected $fillable = [
        'user_id',
        'card_number',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Individual::class, 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'access_card_id');
    }
}
