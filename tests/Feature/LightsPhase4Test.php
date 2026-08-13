<?php

namespace OTGH\AccessControl\Core\Tests\Feature;

use Laravel\Sanctum\Sanctum;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use Tests\TestCase;

class LightsPhase4Test extends TestCase
{
    protected Individual $user;

    protected Area $area;

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

        // Create light with all features
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
     * Test GET /api/lights/{lightId} returns light status
     */
    public function test_get_light_status()
    {
        $response = $this->getJson("/api/lights/{$this->light->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'identifier',
                    'area_id',
                    'area',
                    'state',
                    'state_raw',
                    'brightness',
                    'color',
                    'features',
                    'metadata',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.name', $this->light->name)
            ->assertJsonPath('data.state', 'off')
            ->assertJsonPath('data.brightness', 100)
            ->assertJsonPath('data.features.brightness', true)
            ->assertJsonPath('data.features.color', true);
    }

    /**
     * Test POST /api/lights/{lightId}/on turns light on
     */
    public function test_light_on_command()
    {
        $response = $this->postJson("/api/lights/{$this->light->id}/on", [
            'reason' => 'Manual on via API',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('state', 'on')
            ->assertJsonPath('light', $this->light->identifier);

        $this->light->refresh();
        $this->assertTrue($this->light->state);

        $this->assertDatabaseHas('events', [
            'access_light_id' => $this->light->id,
            'status' => 'api_light_on_requested',
            'origin_type' => 'api',
        ]);
    }

    /**
     * Test POST /api/lights/{lightId}/on with brightness
     */
    public function test_light_on_command_with_brightness()
    {
        $response = $this->postJson("/api/lights/{$this->light->id}/on", [
            'brightness' => 75,
            'reason' => 'Turn on at 75%',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('brightness', 75);

        $this->light->refresh();
        $this->assertTrue($this->light->state);
        $this->assertEquals(75, $this->light->brightness);
    }

    /**
     * Test POST /api/lights/{lightId}/off turns light off
     */
    public function test_light_off_command()
    {
        $this->light->update(['state' => true]);

        $response = $this->postJson("/api/lights/{$this->light->id}/off", [
            'reason' => 'Manual off via API',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('state', 'off');

        $this->light->refresh();
        $this->assertFalse($this->light->state);

        $this->assertDatabaseHas('events', [
            'access_light_id' => $this->light->id,
            'status' => 'api_light_off_requested',
        ]);
    }

    /**
     * Test POST /api/lights/{lightId}/toggle toggles light
     */
    public function test_light_toggle_command()
    {
        $this->assertFalse($this->light->state);

        $response = $this->postJson("/api/lights/{$this->light->id}/toggle");

        $response->assertStatus(202)
            ->assertJsonPath('state', 'on');

        $this->light->refresh();
        $this->assertTrue($this->light->state);
    }

    /**
     * Test PUT /api/lights/{lightId}/brightness sets brightness
     */
    public function test_set_light_brightness()
    {
        $response = $this->putJson("/api/lights/{$this->light->id}/brightness", [
            'brightness' => 50,
            'reason' => 'Set to 50% brightness',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('brightness', 50);

        $this->light->refresh();
        $this->assertEquals(50, $this->light->brightness);

        $this->assertDatabaseHas('events', [
            'access_light_id' => $this->light->id,
            'status' => 'api_light_brightness_set',
        ]);
    }

    /**
     * Test brightness rejects values outside 0-100
     */
    public function test_brightness_validation()
    {
        $response = $this->putJson("/api/lights/{$this->light->id}/brightness", [
            'brightness' => 150,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.brightness.0', 'The brightness must not be greater than 100.');
    }

    /**
     * Test PUT /api/lights/{lightId}/color sets color
     */
    public function test_set_light_color()
    {
        $response = $this->putJson("/api/lights/{$this->light->id}/color", [
            'color' => '#ff0000',
            'reason' => 'Set to red',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('color', '#ff0000');

        $this->light->refresh();
        $this->assertEquals('#ff0000', $this->light->color);

        $this->assertDatabaseHas('events', [
            'access_light_id' => $this->light->id,
            'status' => 'api_light_color_set',
        ]);
    }

    /**
     * Test color validation rejects invalid hex
     */
    public function test_color_validation()
    {
        $response = $this->putJson("/api/lights/{$this->light->id}/color", [
            'color' => 'red',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test brightness denied for light without feature
     */
    public function test_brightness_denied_if_not_supported()
    {
        $basicLight = Light::create([
            'area_id' => $this->area->id,
            'name' => 'Basic Light',
            'identifier' => 'basic_light_'.uniqid(),
            'state' => false,
            'config' => [
                'features' => [
                    'brightness' => false,
                    'color' => false,
                ],
            ],
        ]);

        $response = $this->putJson("/api/lights/{$basicLight->id}/brightness", [
            'brightness' => 75,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'This light does not support brightness control');
    }

    /**
     * Test color denied for light without feature
     */
    public function test_color_denied_if_not_supported()
    {
        $basicLight = Light::create([
            'area_id' => $this->area->id,
            'name' => 'Basic Light',
            'identifier' => 'basic_light_'.uniqid(),
            'state' => false,
            'config' => [
                'features' => [
                    'brightness' => false,
                    'color' => false,
                ],
            ],
        ]);

        $response = $this->putJson("/api/lights/{$basicLight->id}/color", [
            'color' => '#ff0000',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'This light does not support color control');
    }

    /**
     * Test light operations require area permission
     */
    public function test_light_operations_require_permission()
    {
        $otherUser = Individual::create(['name' => 'Other User']);
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/lights/{$this->light->id}/on");

        $response->assertStatus(403);
    }

    /**
     * Test light operations create proper events
     */
    public function test_light_operations_create_events()
    {
        // Turn on
        $this->postJson("/api/lights/{$this->light->id}/on", [
            'brightness' => 80,
        ]);

        // Toggle off
        $this->postJson("/api/lights/{$this->light->id}/toggle");

        // Set brightness
        $this->putJson("/api/lights/{$this->light->id}/brightness", [
            'brightness' => 60,
        ]);

        // Set color
        $this->putJson("/api/lights/{$this->light->id}/color", [
            'color' => '#00ff00',
        ]);

        // Verify all events created
        $events = Event::where('access_light_id', $this->light->id)
            ->pluck('status')
            ->toArray();

        $this->assertContains('api_light_on_requested', $events);
        $this->assertContains('api_light_off_requested', $events);
        $this->assertContains('api_light_brightness_set', $events);
        $this->assertContains('api_light_color_set', $events);
    }

    /**
     * Test light model supports brightness
     */
    public function test_light_supports_brightness()
    {
        $this->assertTrue($this->light->supportsBrightness());
    }

    /**
     * Test light model supports color
     */
    public function test_light_supports_color()
    {
        $this->assertTrue($this->light->supportsColor());
    }

    /**
     * Test light model methods
     */
    public function test_light_model_methods()
    {
        // Test turnOn
        $this->light->turnOn(75);
        $this->light->refresh();
        $this->assertTrue($this->light->state);
        $this->assertEquals(75, $this->light->brightness);

        // Test turnOff
        $this->light->turnOff();
        $this->light->refresh();
        $this->assertFalse($this->light->state);

        // Test setBrightness
        $this->light->setBrightness(40);
        $this->light->refresh();
        $this->assertEquals(40, $this->light->brightness);

        // Test setColor
        $this->light->setColor('#0000ff');
        $this->light->refresh();
        $this->assertEquals('#0000ff', $this->light->color);
    }

    /**
     * Test area has lights relationship
     */
    public function test_area_has_lights_relationship()
    {
        $lights = $this->area->lights()->get();
        $this->assertContains($this->light->id, $lights->pluck('id')->toArray());
    }

    /**
     * Test multiple lights in same area
     */
    public function test_multiple_lights_in_area()
    {
        $light2 = Light::create([
            'area_id' => $this->area->id,
            'name' => 'Living Room Light',
            'identifier' => 'living_room_'.uniqid(),
            'state' => false,
        ]);

        $response = $this->getJson('/api/ha/status');

        $areaData = collect($response->json('areas'))->firstWhere('id', (string) $this->area->id);
        $this->assertEquals(2, count($areaData['devices']['lights']));
    }

    /**
     * Test light metadata is preserved
     */
    public function test_light_metadata_preserved()
    {
        $this->light->update([
            'metadata' => [
                'custom_field' => 'test_value',
                'integration' => 'zigbee',
            ],
        ]);

        $response = $this->getJson("/api/lights/{$this->light->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metadata.custom_field', 'test_value')
            ->assertJsonPath('data.metadata.integration', 'zigbee');
    }
}
