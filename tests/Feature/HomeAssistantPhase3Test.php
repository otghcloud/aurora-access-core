<?php

namespace OTGH\AccessControl\Core\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Individual;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use Tests\TestCase;

class HomeAssistantPhase3Test extends TestCase
{
    protected Individual $user;

    protected Area $area;

    protected Lock $lock;

    protected Sensor $sensor;

    protected Light $light;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and area
        $this->user = Individual::create(['name' => 'Test User']);
        $this->area = Area::create([
            'name' => 'Smart Home',
            'identifier' => 'smart_home_'.uniqid(),
        ]);

        // Grant user access to area
        AreaPermission::create([
            'individual_id' => $this->user->id,
            'area_id' => $this->area->id,
            'permission' => 'allow',
        ]);

        // Create lock
        $this->lock = Lock::create([
            'area_id' => $this->area->id,
            'name' => 'Front Door',
            'identifier' => 'front_door_'.uniqid(),
            'is_primary' => true,
        ]);

        // Create sensor
        $this->sensor = Sensor::create([
            'area_id' => $this->area->id,
            'name' => 'Door Sensor',
            'identifier' => 'door_sensor_'.uniqid(),
            'state' => false,
        ]);

        // Create light
        $this->light = Light::create([
            'area_id' => $this->area->id,
            'name' => 'Hallway Light',
            'identifier' => 'hallway_light_'.uniqid(),
            'state' => false,
            'brightness' => 100,
            'color' => '#ffffff',
            'config' => [
                'features' => [
                    'brightness' => true,
                    'color' => true,
                ],
            ],
        ]);

        Sanctum::actingAs($this->user);
    }

    /**
     * Test GET /api/ha/status returns all areas with devices
     */
    public function test_ha_status_endpoint_returns_areas_and_devices()
    {
        $response = $this->getJson('/api/ha/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
                'areas' => [
                    '*' => [
                        'id',
                        'name',
                        'identifier',
                        'devices' => [
                            'locks' => ['*' => ['id', 'name', 'state']],
                            'sensors' => ['*' => ['id', 'name', 'state']],
                            'lights' => ['*' => ['id', 'name', 'state']],
                        ],
                    ],
                ],
                'device_count',
            ]);

        $areaData = collect($response->json('areas'))->firstWhere('id', (string) $this->area->id);
        $this->assertNotNull($areaData);
        $this->assertEquals($this->area->name, $areaData['name']);
    }

    public function test_ha_status_includes_resolved_autolock_settings()
    {
        $this->area->update([
            'config' => ['locking' => ['autolock_enabled' => true, 'autolock_duration' => 45]],
        ]);

        $this->getJson('/api/ha/status')
            ->assertOk()
            ->assertJsonPath('areas.0.devices.locks.0.autolock.enabled', true)
            ->assertJsonPath('areas.0.devices.locks.0.autolock.duration_seconds', 45)
            ->assertJsonPath('areas.0.devices.locks.0.autolock.source', 'area_default');

        $this->lock->update([
            'config' => ['locking' => ['autolock_override_enabled' => false, 'autolock_override_duration' => 90]],
        ]);

        $this->getJson('/api/ha/status')
            ->assertOk()
            ->assertJsonPath('areas.0.devices.locks.0.autolock.enabled', false)
            ->assertJsonPath('areas.0.devices.locks.0.autolock.duration_seconds', 90)
            ->assertJsonPath('areas.0.devices.locks.0.autolock.source', 'lock_override');
    }

    /**
     * Test GET /api/ha/status/{areaId} returns single area status
     */
    public function test_ha_status_endpoint_single_area()
    {
        $response = $this->getJson("/api/ha/status/{$this->area->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
                'area' => [
                    'id',
                    'name',
                    'identifier',
                    'devices',
                ],
            ])
            ->assertJsonPath('area.name', $this->area->name);
    }

    /**
     * Test GET /api/ha/discovery returns all MQTT discovery manifests
     */
    public function test_ha_discovery_returns_manifests()
    {
        $response = $this->getJson('/api/ha/discovery');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
                'manifests' => [
                    '*' => [
                        'topic',
                        'payload' => ['unique_id', 'device', 'state_topic'],
                        'retain',
                        'qos',
                    ],
                ],
                'count',
            ]);

        $this->assertGreaterThanOrEqual(3, $response->json('count')); // Lock + sensor + light
    }

    /**
     * Test GET /api/ha/discovery/locks/{lockId} returns lock discovery manifest
     */
    public function test_ha_lock_discovery()
    {
        $response = $this->getJson("/api/ha/discovery/locks/{$this->lock->id}");

        $response->assertStatus(200)
            ->assertJsonPath('payload.type', 'lock')
            ->assertJsonPath('payload.unique_id', 'aurora_lock_'.$this->lock->id)
            ->assertJsonPath('payload.name', $this->lock->name)
            ->assertJsonPath('retain', true);
    }

    /**
     * Test GET /api/ha/discovery/sensors/{sensorId} returns sensor discovery manifest
     */
    public function test_ha_sensor_discovery()
    {
        $response = $this->getJson("/api/ha/discovery/sensors/{$this->sensor->id}");

        $response->assertStatus(200)
            ->assertJsonPath('payload.type', 'binary_sensor')
            ->assertJsonPath('payload.unique_id', 'aurora_sensor_'.$this->sensor->id)
            ->assertJsonPath('payload.device_class', 'door')
            ->assertJsonPath('retain', true);
    }

    /**
     * Test GET /api/ha/discovery/lights/{lightId} returns light discovery manifest
     */
    public function test_ha_light_discovery()
    {
        $response = $this->getJson("/api/ha/discovery/lights/{$this->light->id}");

        $response->assertStatus(200)
            ->assertJsonPath('payload.type', 'light')
            ->assertJsonPath('payload.unique_id', 'aurora_light_'.$this->light->id)
            ->assertJsonPath('payload.brightness_state_topic', 'aurora/lights/'.$this->light->id.'/brightness');
    }

    /**
     * Test POST /api/ha/webhook with lock_command
     */
    public function test_ha_webhook_lock_command()
    {
        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'lock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'lock',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'lock');

        $this->assertDatabaseHas('events', [
            'access_lock_id' => $this->lock->id,
            'status' => 'ha_lock_requested',
            'origin_type' => 'ha_webhook',
        ]);
    }

    /**
     * Test POST /api/ha/webhook with unlock command
     */
    public function test_ha_webhook_unlock_command()
    {
        Queue::fake();

        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'lock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'unlock',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'unlock');

        $this->assertDatabaseHas('events', [
            'access_lock_id' => $this->lock->id,
            'status' => 'ha_unlock_requested',
        ]);

        Queue::assertPushed(ProcessReaderEvent::class, function (ProcessReaderEvent $job): bool {
            return $job->accessReader->area_id === $this->area->id
                && $job->targetValue === 0
                && $job->allowAutoRelock
                && $job->eventSource === 'ha_webhook';
        });
    }

    public function test_ha_webhook_updates_autolock_enabled_and_preserves_duration()
    {
        $this->area->update([
            'config' => ['locking' => ['autolock_enabled' => false, 'autolock_duration' => 30]],
        ]);

        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'set_enabled',
            'area_id' => $this->area->id,
            'value' => '1',
        ]);

        $response->assertOk()
            ->assertJsonPath('autolock.enabled', true)
            ->assertJsonPath('autolock.duration_seconds', 30)
            ->assertJsonPath('autolock.source', 'lock_override');

        $this->lock->refresh();
        $this->assertTrue((bool) data_get($this->lock->config, 'locking.autolock_override_enabled'));
        $this->assertSame(30, (int) data_get($this->lock->config, 'locking.autolock_override_duration'));
        $this->assertDatabaseHas('events', [
            'access_lock_id' => $this->lock->id,
            'status' => 'ha_autolock_updated',
            'origin_type' => 'ha_webhook',
        ]);
    }

    public function test_ha_webhook_updates_autolock_duration_and_preserves_enabled_state()
    {
        $this->area->update([
            'config' => ['locking' => ['autolock_enabled' => true, 'autolock_duration' => 15]],
        ]);

        $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'set_duration',
            'area_id' => $this->area->id,
            'value' => '3600',
        ])->assertOk()
            ->assertJsonPath('autolock.enabled', true)
            ->assertJsonPath('autolock.duration_seconds', 3600);

        $this->lock->refresh();
        $this->assertTrue((bool) data_get($this->lock->config, 'locking.autolock_override_enabled'));
        $this->assertSame(3600, (int) data_get($this->lock->config, 'locking.autolock_override_duration'));
    }

    public function test_ha_webhook_accepts_zero_autolock_duration()
    {
        $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'set_duration',
            'area_id' => $this->area->id,
            'value' => '0',
        ])->assertOk()
            ->assertJsonPath('autolock.duration_seconds', 0);

        $this->lock->refresh();
        $this->assertSame(0, (int) data_get($this->lock->config, 'locking.autolock_override_duration'));
    }

    public function test_ha_webhook_rejects_autolock_cross_area_device()
    {
        $otherArea = Area::create([
            'name' => 'Other Area',
            'identifier' => 'other_area_'.uniqid(),
        ]);
        AreaPermission::create([
            'individual_id' => $this->user->id,
            'area_id' => $otherArea->id,
            'permission' => 'allow',
        ]);

        $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'set_enabled',
            'area_id' => $otherArea->id,
            'value' => '1',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('events', ['status' => 'ha_autolock_updated']);
    }

    public function test_ha_webhook_validates_autolock_commands()
    {
        foreach (['-1', '3601', '1.5', 'invalid'] as $value) {
            $this->postJson('/api/ha/webhook', [
                'type' => 'autolock_command',
                'device_id' => 'aurora_lock_'.$this->lock->id,
                'action' => 'set_duration',
                'area_id' => $this->area->id,
                'value' => $value,
            ])->assertUnprocessable();
        }

        $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'set_enabled',
            'area_id' => $this->area->id,
            'value' => 'yes',
        ])->assertUnprocessable();

        $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'unknown',
            'area_id' => $this->area->id,
            'value' => '1',
        ])->assertBadRequest();

        $this->postJson('/api/ha/webhook', [
            'type' => 'autolock_command',
            'device_id' => 'invalid',
            'action' => 'set_enabled',
            'area_id' => $this->area->id,
            'value' => '1',
        ])->assertBadRequest();

        $this->assertDatabaseMissing('events', ['status' => 'ha_autolock_updated']);
    }

    /**
     * Test POST /api/ha/webhook with light_command on
     */
    public function test_ha_webhook_light_on_command()
    {
        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'light_command',
            'device_id' => 'aurora_light_'.$this->light->id,
            'action' => 'on',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'on');

        $this->light->refresh();
        $this->assertTrue($this->light->state);

        $this->assertDatabaseHas('events', [
            'access_light_id' => $this->light->id,
            'status' => 'ha_light_on_requested',
            'origin_type' => 'ha_webhook',
        ]);
    }

    /**
     * Test POST /api/ha/webhook with light_command off
     */
    public function test_ha_webhook_light_off_command()
    {
        $this->light->update(['state' => true]);

        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'light_command',
            'device_id' => 'aurora_light_'.$this->light->id,
            'action' => 'off',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('action', 'off');

        $this->light->refresh();
        $this->assertFalse($this->light->state);
    }

    /**
     * Test POST /api/ha/webhook with light brightness command
     */
    public function test_ha_webhook_light_brightness_command()
    {
        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'light_command',
            'device_id' => 'aurora_light_'.$this->light->id,
            'action' => 'brightness',
            'area_id' => $this->area->id,
            'value' => '75',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('brightness', 75);

        $this->light->refresh();
        $this->assertEquals(75, $this->light->brightness);
    }

    /**
     * Test POST /api/ha/webhook with light color command
     */
    public function test_ha_webhook_light_color_command()
    {
        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'light_command',
            'device_id' => 'aurora_light_'.$this->light->id,
            'action' => 'color',
            'area_id' => $this->area->id,
            'value' => '#ff0000',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('color', '#ff0000');

        $this->light->refresh();
        $this->assertEquals('#ff0000', $this->light->color);
    }

    /**
     * Test POST /api/ha/webhook with sensor_query
     */
    public function test_ha_webhook_sensor_query()
    {
        $this->sensor->update(['state' => true]);

        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'sensor_query',
            'device_id' => 'aurora_sensor_'.$this->sensor->id,
            'action' => 'query',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('state', 'on')
            ->assertJsonPath('state_raw', true);
    }

    /**
     * Test HA webhook denies unauthorized user
     */
    public function test_ha_webhook_denies_unauthorized_user()
    {
        $otherUser = Individual::create(['name' => 'Other User']);
        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'lock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'lock',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test HA webhook requires authentication
     */
    public function test_ha_webhook_requires_authentication()
    {
        // Remove middleware context
        $this->withoutMiddleware();

        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'lock_command',
            'device_id' => 'aurora_lock_'.$this->lock->id,
            'action' => 'lock',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test HA status only shows areas user has access to
     */
    public function test_ha_status_filters_by_user_permission()
    {
        // Create another area user doesn't have access to
        $otherArea = Area::create([
            'name' => 'Restricted Area',
            'identifier' => 'restricted_'.uniqid(),
        ]);

        $response = $this->getJson('/api/ha/status');

        $response->assertStatus(200);
        $areaIds = collect($response->json('areas'))->pluck('id')->toArray();

        $this->assertContains((string) $this->area->id, $areaIds);
        $this->assertNotContains((string) $otherArea->id, $areaIds);
    }

    /**
     * Test HA webhook with invalid device_id format
     */
    public function test_ha_webhook_rejects_invalid_device_id()
    {
        $response = $this->postJson('/api/ha/webhook', [
            'type' => 'lock_command',
            'device_id' => 'invalid_device_id',
            'action' => 'lock',
            'area_id' => $this->area->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Invalid device ID');
    }

    /**
     * Test HA discovery endpoint structure for all device types
     */
    public function test_ha_discovery_includes_all_device_types()
    {
        $response = $this->getJson('/api/ha/discovery');

        $manifests = collect($response->json('manifests'));

        // Find each device type
        $lockManifest = $manifests->first(fn ($m) => $m['payload']['type'] === 'lock');
        $sensorManifest = $manifests->first(fn ($m) => $m['payload']['type'] === 'binary_sensor');
        $lightManifest = $manifests->first(fn ($m) => $m['payload']['type'] === 'light');

        $this->assertNotNull($lockManifest, 'No lock manifest found');
        $this->assertNotNull($sensorManifest, 'No sensor manifest found');
        $this->assertNotNull($lightManifest, 'No light manifest found');
    }
}
