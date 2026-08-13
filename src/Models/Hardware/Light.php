<?php

namespace OTGH\AccessControl\Core\Models\Hardware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Event;

class Light extends Model
{
    protected $table = 'access_lights';

    protected $fillable = [
        'area_id',
        'name',
        'identifier',
        'state',
        'brightness',
        'color',
        'config',
        'metadata',
    ];

    protected $casts = [
        'state' => 'boolean',
        'brightness' => 'integer',
        'config' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Light belongs to an area
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /**
     * Light has many events
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'access_light_id');
    }

    /**
     * Get human-readable state
     */
    public function getStateDisplayAttribute(): string
    {
        return $this->state ? 'on' : 'off';
    }

    /**
     * Check if light has brightness support
     */
    public function supportsBrightness(): bool
    {
        return (bool) data_get($this->config, 'features.brightness', false);
    }

    /**
     * Check if light has color support
     */
    public function supportsColor(): bool
    {
        return (bool) data_get($this->config, 'features.color', false);
    }

    /**
     * Turn light on
     */
    public function turnOn(?int $brightness = null): void
    {
        $this->update([
            'state' => true,
            'brightness' => $brightness ?? $this->brightness ?? 100,
        ]);
    }

    /**
     * Turn light off
     */
    public function turnOff(): void
    {
        $this->update(['state' => false]);
    }

    /**
     * Set brightness (0-100)
     */
    public function setBrightness(int $brightness): void
    {
        $brightness = max(0, min(100, $brightness));
        $this->update(['brightness' => $brightness]);
    }

    /**
     * Set color (hex format: #RRGGBB)
     */
    public function setColor(string $color): void
    {
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Color must be in hex format: #RRGGBB');
        }

        $this->update(['color' => $color]);
    }

    /**
     * Get light location for queries
     */
    public function getLocationAttribute(): string
    {
        return $this->area->name.' - '.$this->name;
    }
}
