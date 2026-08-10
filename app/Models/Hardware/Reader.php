<?php

namespace App\Models\Hardware;

use App\Models\Access\Area;
use App\Models\Access\Event;
use App\Models\BaseModel;
use App\Support\AccessControlMqttTopic;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function mqttReaderSlug(): string
    {
        return AccessControlMqttTopic::readerSlug($this);
    }

    public function mqttBaseTopic(): string
    {
        return AccessControlMqttTopic::baseTopic($this);
    }

    public function mqttCommandTopic(): string
    {
        return AccessControlMqttTopic::commandTopic($this);
    }

    public function mqttStateTopic(): string
    {
        return AccessControlMqttTopic::stateTopic($this);
    }

    public function mqttEventsTopic(): string
    {
        return AccessControlMqttTopic::eventsTopic($this);
    }
}
