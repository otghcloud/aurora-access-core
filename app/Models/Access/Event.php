<?php

namespace App\Models\Access;

use App\Enums\AccessControl\AccessEventStatus;
use App\Models\BaseModel;
use App\Models\Hardware\Lock;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Event extends BaseModel
{
    protected $fillable = [
        'access_card_id',
        'access_area_id',
        'access_lock_id',
        'access_source_id',
        'user_id',
        'card_number',
        'origin_type',
        'origin_id',
        'origin_label',
        'granted',
        'status',
        'reason',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'granted' => 'boolean',
            'origin_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->applyOriginDefaults();
        });
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?string {
                return AccessEventStatus::keyFor($value);
            },
            set: function (mixed $value): ?int {
                $normalized = AccessEventStatus::normalizeValue($value);

                if ($normalized !== null) {
                    return $normalized;
                }

                if ($value === null || (is_string($value) && trim($value) === '')) {
                    return null;
                }

                throw new InvalidArgumentException('Invalid access event status value.');
            }
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return AccessEventStatus::labelFor($this->attributes['status'] ?? null);
    }

    public function accessCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'access_card_id');
    }

    public function originReader(): BelongsTo
    {
        return $this->belongsTo(Reader::class, 'origin_id');
    }

    public function accessArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'access_area_id');
    }

    public function accessLock(): BelongsTo
    {
        return $this->belongsTo(Lock::class, 'access_lock_id');
    }

    public function accessSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'access_source_id');
    }

    public function accessUser(): BelongsTo
    {
        return $this->belongsTo(Individual::class, 'user_id');
    }

    private function applyOriginDefaults(): void
    {
        $reader = ($this->origin_type === 'reader' && $this->origin_id !== null)
            ? ($this->relationLoaded('originReader') ? $this->originReader : Reader::query()->find((int) $this->origin_id))
            : null;
        $lock = $this->relationLoaded('accessLock')
            ? $this->accessLock
            : ($this->access_lock_id ? Lock::query()->find($this->access_lock_id) : null);
        $area = $this->relationLoaded('accessArea')
            ? $this->accessArea
            : ($this->access_area_id ? Area::query()->find($this->access_area_id) : null);
        $source = $this->relationLoaded('accessSource')
            ? $this->accessSource
            : ($this->access_source_id ? Source::query()->find($this->access_source_id) : null);

        if ($this->access_area_id === null && $reader?->area_id) {
            $this->access_area_id = $reader->area_id;
        }

        if (blank($this->origin_type) || $this->origin_id === null) {
            if ($lock !== null || $this->access_lock_id !== null) {
                $this->origin_type = 'lock';
                $this->origin_id = (int) ($lock?->id ?? $this->access_lock_id);
                $this->origin_label = $this->origin_label ?: ($lock?->name ?: $lock?->identifier);

                return;
            }

            if ($reader !== null) {
                $this->origin_type = 'reader';
                $this->origin_id = (int) $reader->id;
                $this->origin_label = $this->origin_label ?: ($reader->name ?: $reader->identifier);

                return;
            }

            if ($area !== null || $this->access_area_id !== null) {
                $this->origin_type = 'area';
                $this->origin_id = (int) ($area?->id ?? $this->access_area_id);
                $this->origin_label = $this->origin_label ?: ($area?->name ?: $area?->identifier);

                return;
            }

            if ($source !== null || $this->access_source_id !== null) {
                $this->origin_type = 'source';
                $this->origin_id = (int) ($source?->id ?? $this->access_source_id);
                $this->origin_label = $this->origin_label ?: ($source?->name ?: $source?->identifier);
            }
        }

        if (blank($this->origin_label) && $this->origin_id !== null) {
            $this->origin_label = match ($this->origin_type) {
                'lock' => $lock?->name ?: $lock?->identifier ?: ('Lock #'.$this->origin_id),
                'reader' => $reader?->name ?: $reader?->identifier ?: ('Reader #'.$this->origin_id),
                'area' => $area?->name ?: $area?->identifier ?: ('Area #'.$this->origin_id),
                'source' => $source?->name ?: $source?->identifier ?: ('Source #'.$this->origin_id),
                default => $this->origin_label,
            };
        }
    }
}
