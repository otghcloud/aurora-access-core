<?php

use App\Enums\AccessControl\AccessBindingActionKey;
use App\Models\Access\Area;
use App\Models\Hardware\AdapterBinding;
use App\Models\Hardware\Sensor;
use App\Models\Hardware\Source;
use App\Models\User;
use App\Services\AccessControlMqttPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and lists sensors for an area', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Main Entrance',
        'identifier' => 'main-entrance',
        'metadata' => [],
    ]);

    $response = $this->actingAs($admin)->post(route('admin.access-sensors.store'), [
        'area_id' => $area->id,
        'name' => 'Front Door Sensor',
        'identifier' => 'front-door-sensor',
        'state' => '1',
        'config_json' => '{"sensor":{"kind":"door_open"}}',
        'metadata_json' => '{}',
    ]);

    $response->assertRedirect(route('admin.access-sensors.index'));

    $sensor = Sensor::query()->where('identifier', 'front-door-sensor')->firstOrFail();

    expect($sensor->area_id)->toBe($area->id)
        ->and($sensor->state)->toBeTrue()
        ->and($sensor->config['sensor']['kind'])->toBe('door_open');

    $this->actingAs($admin)->get(route('admin.access-sensors.index'))
        ->assertOk()
        ->assertSee('Front Door Sensor');
});

it('allows sensors to be selected as adapter binding targets and publishes MQTT state', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'East Wing',
        'identifier' => 'east-wing',
        'metadata' => [],
    ]);

    $sensor = Sensor::create([
        'area_id' => $area->id,
        'name' => 'Door Open Sensor',
        'identifier' => 'door-open-sensor',
        'state' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $source = Source::create([
        'name' => 'MQTT Source',
        'identifier' => 'mqtt-source',
        'type' => 'mqtt',
        'endpoint' => 'mqtt://127.0.0.1',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $publisher = Mockery::mock(AccessControlMqttPublisher::class);
    $publisher->shouldReceive('publishSensorState')->once()->withArgs(function (Sensor $target) use ($sensor): bool {
        return $target->id === $sensor->id;
    });
    $this->app->instance(AccessControlMqttPublisher::class, $publisher);

    $response = $this->actingAs($admin)->post(route('admin.access-bindings.store'), [
        'direction' => 'input',
        'adapter_type' => 'mqtt',
        'target_type' => 'sensor',
        'target_id' => $sensor->id,
        'source_id' => $source->id,
        'action_key' => (string) AccessBindingActionKey::DOORBELL->value,
        'channel' => 'door-open',
        'signal_reversed' => '0',
        'enabled' => '1',
        'config_json' => '{}',
        'metadata_json' => '{}',
    ]);

    $response->assertRedirect(route('admin.access-bindings.index'));

    $binding = AdapterBinding::query()->where('target_type', 'sensor')->where('target_id', $sensor->id)->firstOrFail();
    expect($binding->adapter_type)->toBe('mqtt');
    expect($binding->channel)->toBe('door-open');
});
