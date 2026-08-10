<?php

namespace App\Models\Hardware;

use App\Enums\AccessControl\AccessBindingActionKey;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdapterBinding extends BaseModel
{
    protected $fillable = [
        'source_id',
        'direction',
        'adapter_type',
        'target_type',
        'target_id',
        'action_key',
        'channel',
        'signal_reversed',
        'enabled',
        'config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'signal_reversed' => 'boolean',
            'enabled' => 'boolean',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function isInput(): bool
    {
        return strtolower($this->direction) === 'input';
    }

    public function isOutput(): bool
    {
        return strtolower($this->direction) === 'output';
    }

    public function actionKeyEnum(): ?AccessBindingActionKey
    {
        return AccessBindingActionKey::fromStored($this->action_key);
    }

    public function actionKeyName(): ?string
    {
        return $this->actionKeyEnum()?->key();
    }
}
