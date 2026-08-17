<?php

namespace OTGH\AccessControl\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;

class ProcessLightEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $lightId,
        protected string $action,
        protected ?int $brightness = null,
        protected ?string $color = null,
        protected string $originType = 'api',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AccessControlMqttPublisher $mqttPublisher): void
    {
        $light = Light::find($this->lightId);

        if (! $light) {
            \Log::warning('Light not found for ProcessLightEvent', [
                'light_id' => $this->lightId,
                'action' => $this->action,
            ]);

            return;
        }

        try {
            match ($this->action) {
                'on' => $this->handleOn($light),
                'off' => $this->handleOff($light),
                'brightness' => $this->handleBrightness($light),
                'color' => $this->handleColor($light),
                default => \Log::warning('Unknown light action: '.$this->action),
            };

            $mqttPublisher->publishLightState($light->fresh() ?? $light);
        } catch (\Exception $e) {
            \Log::error('ProcessLightEvent error: '.$e->getMessage(), [
                'light_id' => $this->lightId,
                'action' => $this->action,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Handle turn on action
     */
    protected function handleOn(Light $light): void
    {
        $light->turnOn($this->brightness);

        \Log::info('Light turned on via '.$this->originType, [
            'light_id' => $light->id,
            'brightness' => $this->brightness,
        ]);
    }

    /**
     * Handle turn off action
     */
    protected function handleOff(Light $light): void
    {
        $light->turnOff();

        \Log::info('Light turned off via '.$this->originType, [
            'light_id' => $light->id,
        ]);
    }

    /**
     * Handle brightness action
     */
    protected function handleBrightness(Light $light): void
    {
        if ($this->brightness === null) {
            \Log::warning('Brightness not provided for ProcessLightEvent', [
                'light_id' => $light->id,
            ]);

            return;
        }

        $light->setBrightness($this->brightness);

        \Log::info('Light brightness set via '.$this->originType, [
            'light_id' => $light->id,
            'brightness' => $this->brightness,
        ]);
    }

    /**
     * Handle color action
     */
    protected function handleColor(Light $light): void
    {
        if ($this->color === null) {
            \Log::warning('Color not provided for ProcessLightEvent', [
                'light_id' => $light->id,
            ]);

            return;
        }

        $light->setColor($this->color);

        \Log::info('Light color set via '.$this->originType, [
            'light_id' => $light->id,
            'color' => $this->color,
        ]);
    }
}
