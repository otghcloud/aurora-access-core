<?php

namespace OTGH\AccessControl\Core\Tests\Feature;

use Laravel\Sanctum\Sanctum;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use Tests\TestCase;

class HttpApiPhase2Test extends TestCase
{
    protected Individual $user;

    protected Area $area;

    protected Lock $lock1;

    protected Lock $lock2;

    protected Reader $reader;

    protected Sensor $sensor;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and area
        $this->user = Individual::create(['name' => 'Test User']);
        $this->area = Area::create([
            'name' => 'Test Area',
            'identifier' => 'test_area_'.uniqid(),
        ]);

        // Grant user access to area
        AreaPermission::create([
            'individual_id' => $this->user->id,
            'area_id' => $this->area->id,
            'permission' => 'allow',
        ]);

        // Create locks
        $this->lock1 = Lock::create([
            'area_id' => $this->area->id,
            'name' => 'Test Lock 1',
            'identifier' => 'test_lock_1_'.uniqid(),
            'is_primary' => true,
        ]);

        $this->lock2 = Lock::create([
            'area_id' => $this->area->id,
            'name' => 'Test Lock 2',
            'identifier' => 'test_lock_2_'.uniqid(),
            'is_primary' => false,
        ]);

        // Create reader
        $this->reader = Reader::create([
            'name' => 'Test Reader',
            'identifier' => 'test_reader_'.uniqid(),
            'area_id' => $this->area->id,
        ]);

        // Bind reader to lock1
        ReaderLockBinding::create([
            'reader_id' => $this->reader->id,
            'lock_id' => $this->lock1->id,
            'area_id' => $this->area->id,
        ]);

        // Create sensor
        $this->sensor = Sensor::create([
            'area_id' => $this->area->id,
            'name' => 'Test Sensor',
            'identifier' => 'test_sensor_'.uniqid(),
            'state' => true,
        ]);

        // Authenticate as user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test GET /api/status returns all areas and hardware
     */
    public function test_status_endpoint_returns_full_hierarchy()
    {
        $response = $this->getJson('/api/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'areas' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'readers' => [
                            '*' => ['id', 'name', 'identifier', 'state', 'bound_locks'],
                        ],
                        'locks' => [
                            '*' => ['id', 'name', 'identifier', 'state', 'autolock'],
                        ],
                        'sensors' => [
                            '*' => ['id', 'name', 'identifier', 'state', 'state_raw'],
                        ],
                        'config' => [
                            'autolock' => ['enabled', 'duration_seconds'],
                        ],
                        'timestamp',
                    ],
                ],
                'timestamp',
                'health',
            ]);

        $data = $response->json('areas.0');
        $this->assertEquals($this->area->name, $data['name']);
        $this->assertEquals($this->area->identifier, $data['slug']);
    }

    /**
     * Test GET /api/status includes reader lock bindings
     */
    public function test_status_includes_reader_lock_bindings()
    {
        $response = $this->getJson('/api/status');

        $response->assertStatus(200);
        $readers = $response->json('areas.0.readers');

        $this->assertNotEmpty($readers);
        $this->assertContains((string) $this->lock1->id, $readers[0]['bound_locks']);
    }

    /**
     * Test GET /api/locks/{lock} returns lock status
     */
    public function test_get_lock_status()
    {
        $response = $this->getJson("/api/locks/{$this->lock1->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'identifier',
                    'area',
                    'state',
                    'autolock',
                    'adapter_bindings_count',
                ],
            ])
            ->assertJsonPath('data.name', $this->lock1->name)
            ->assertJsonPath('data.identifier', $this->lock1->identifier);
    }

    /**
     * Test POST /api/locks/{lock}/lock creates event and queues job
     */
    public function test_lock_command_creates_event()
    {
        $response = $this->postJson("/api/locks/{$this->lock1->id}/lock", [
            'reason' => 'Testing lock command',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('lock', $this->lock1->identifier)
            ->assertJsonPath('status', 'locked');

        $this->assertDatabaseHas('events', [
            'access_lock_id' => $this->lock1->id,
            'status' => 'api_lock_requested',
            'reason' => 'Testing lock command',
        ]);
    }

    /**
     * Test POST /api/locks/{lock}/unlock creates event
     */
    public function test_unlock_command_creates_event()
    {
        $response = $this->postJson("/api/locks/{$this->lock1->id}/unlock", [
            'reason' => 'Testing unlock command',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'unlocked');

        $this->assertDatabaseHas('events', [
            'access_lock_id' => $this->lock1->id,
            'status' => 'api_unlock_requested',
        ]);
    }

    /**
     * Test POST /api/locks/{lock}/toggle creates event
     */
    public function test_toggle_command_creates_event()
    {
        $response = $this->postJson("/api/locks/{$this->lock1->id}/toggle");

        $response->assertStatus(202);

        $this->assertDatabaseHas('events', [
            'access_lock_id' => $this->lock1->id,
            'status' => 'api_toggle_requested',
        ]);
    }

    /**
     * Test PUT /api/locks/{lock}/autolock updates config
     */
    public function test_update_lock_autolock()
    {
        $response = $this->putJson("/api/locks/{$this->lock1->id}/autolock", [
            'enabled' => true,
            'duration_seconds' => 30,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('autolock.enabled', true)
            ->assertJsonPath('autolock.duration_seconds', 30);

        $this->lock1->refresh();
        $this->assertTrue((bool) data_get($this->lock1->config, 'locking.autolock_override_enabled'));
        $this->assertEquals(30, (int) data_get($this->lock1->config, 'locking.autolock_override_duration'));
    }

    /**
     * Test GET /api/sensors lists sensors
     */
    public function test_get_sensors_list()
    {
        $response = $this->getJson('/api/sensors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'identifier',
                        'area_id',
                        'state',
                        'state_raw',
                        'updated_at',
                        'metadata',
                    ],
                ],
                'count',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('count'));
    }

    /**
     * Test GET /api/sensors?area_id=X filters by area
     */
    public function test_get_sensors_filtered_by_area()
    {
        $response = $this->getJson("/api/sensors?area_id={$this->area->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals((string) $this->sensor->id, $data[0]['id']);
    }

    /**
     * Test GET /api/sensors/{sensor} returns specific sensor
     */
    public function test_get_specific_sensor()
    {
        $response = $this->getJson("/api/sensors/{$this->sensor->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $this->sensor->name)
            ->assertJsonPath('data.state_raw', true);
    }

    /**
     * Test GET /api/areas/{area} returns area status
     */
    public function test_get_area_status()
    {
        $response = $this->getJson("/api/areas/{$this->area->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'readers',
                    'locks',
                    'sensors',
                    'config',
                ],
            ])
            ->assertJsonPath('data.name', $this->area->name);
    }

    /**
     * Test PUT /api/areas/{area}/autolock updates area config
     */
    public function test_update_area_autolock()
    {
        $response = $this->putJson("/api/areas/{$this->area->id}/autolock", [
            'enabled' => true,
            'duration_seconds' => 45,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('autolock.enabled', true)
            ->assertJsonPath('autolock.duration_seconds', 45);

        $this->area->refresh();
        $this->assertTrue((bool) data_get($this->area->config, 'locking.autolock_enabled'));
        $this->assertEquals(45, (int) data_get($this->area->config, 'locking.autolock_duration'));
    }

    /**
     * Test unauthorized access is denied
     */
    public function test_unauthorized_user_denied_access()
    {
        $otherUser = Individual::create(['name' => 'Other User']);
        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/areas/{$this->area->id}");

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated requests are rejected
     */
    public function test_unauthenticated_request_rejected()
    {
        // Remove authentication
        $this->withoutMiddleware();

        $response = $this->getJson('/api/status');

        $response->assertStatus(401);
    }

    /**
     * Test lock command requires area permission
     */
    public function test_lock_command_requires_permission()
    {
        $otherArea = Area::create([
            'name' => 'Other Area',
            'identifier' => 'other_area_'.uniqid(),
        ]);

        $otherLock = Lock::create([
            'area_id' => $otherArea->id,
            'name' => 'Other Lock',
            'identifier' => 'other_lock_'.uniqid(),
        ]);

        $response = $this->postJson("/api/locks/{$otherLock->id}/lock");

        $response->assertStatus(403);
    }

    /**
     * Test area autolock creates event with correct metadata
     */
    public function test_area_autolock_creates_event_with_metadata()
    {
        $this->putJson("/api/areas/{$this->area->id}/autolock", [
            'enabled' => true,
            'duration_seconds' => 60,
        ]);

        $event = Event::where('access_area_id', $this->area->id)
            ->where('status', 'api_autolock_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertTrue((bool) data_get($event->metadata, 'autolock_enabled'));
        $this->assertEquals(60, (int) data_get($event->metadata, 'autolock_duration'));
        $this->assertEquals('area_default', data_get($event->metadata, 'autolock_scope'));
    }

    /**
     * Test multiple sensors can be retrieved
     */
    public function test_multiple_sensors_in_response()
    {
        $sensor2 = Sensor::create([
            'area_id' => $this->area->id,
            'name' => 'Second Sensor',
            'identifier' => 'sensor_2_'.uniqid(),
            'state' => false,
        ]);

        $response = $this->getJson('/api/sensors');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $response->json('count'));
    }
}
