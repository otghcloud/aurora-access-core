<?php

use App\Enums\AccessControl\AccessBindingActionKey;
use App\Models\Access\Area;
use App\Models\Hardware\AdapterBinding;
use App\Models\Hardware\Lock;
use App\Models\Hardware\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters bindings index by adapter and direction', function () {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Server Area',
        'identifier' => 'server-area',
        'metadata' => [],
    ]);

    $lock = Lock::create([
        'area_id' => $area->id,
        'name' => 'Server Door Lock',
        'identifier' => 'server-door-lock',
        'is_primary' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $source = Source::create([
        'name' => 'Automation MQTT',
        'identifier' => 'automation-mqtt',
        'type' => 'mqtt',
        'endpoint' => 'mqtt://broker:1883',
        'enabled' => true,
        'config' => ['mqtt' => ['host' => 'broker', 'port' => 1883]],
        'metadata' => [],
    ]);

    AdapterBinding::create([
        'source_id' => $source->id,
        'direction' => 'output',
        'adapter_type' => 'mqtt',
        'target_type' => 'lock',
        'target_id' => $lock->id,
        'action_key' => AccessBindingActionKey::LOCK_POWER->value,
        'channel' => 'locks/server/power',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    AdapterBinding::create([
        'source_id' => null,
        'direction' => 'input',
        'adapter_type' => 'http',
        'target_type' => 'area',
        'target_id' => $area->id,
        'action_key' => AccessBindingActionKey::DOORBELL->value,
        'channel' => null,
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.access-bindings.index', [
        'direction' => 'output',
        'adapter_type' => 'mqtt',
    ]));

    $response->assertOk();
    $response->assertSee('LockPower');
    $response->assertSee('Server Door Lock');
    $response->assertDontSee('<code>Doorbell</code>', false);
});
