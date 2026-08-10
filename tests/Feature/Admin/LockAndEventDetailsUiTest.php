<?php

use App\Enums\AccessControl\AccessEventStatus;
use App\Models\Access\Area;
use App\Models\Access\Event;
use App\Models\Hardware\Lock;
use App\Models\Hardware\Reader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hides raw config and metadata json on lock details page', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Office',
        'identifier' => 'office',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Office Reader',
        'identifier' => 'ttyUSB14',
        'area_id' => $area->id,
        'config' => [
            'general' => [
                'autolock_enabled' => true,
                'autolock_duration' => 10,
                'feedback_state_duration' => 5,
                'reader_mode' => 'card_only',
            ],
            'serial' => [],
        ],
        'metadata' => [],
    ]);

    $lock = Lock::create([
        'area_id' => $area->id,
        'name' => 'Office Door Maglock',
        'identifier' => 'od-maglock',
        'is_primary' => true,
        'config' => ['internal' => ['secret' => 'value']],
        'metadata' => ['diagnostics' => ['foo' => 'bar']],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.access-locks.show', $lock));

    $response->assertOk();
    $response->assertDontSee('Config JSON');
    $response->assertDontSee('Metadata JSON');
    $response->assertDontSee('"secret"');
    $response->assertDontSee('"diagnostics"');

    expect($reader->id)->toBeInt();
});

it('does not render raw event metadata json payload on event details page', function (): void {
    $admin = User::factory()->create();

    $reader = Reader::create([
        'name' => 'Event Reader',
        'identifier' => 'ttyUSB15',
        'area_id' => null,
        'config' => [
            'general' => [
                'autolock_enabled' => false,
                'autolock_duration' => 0,
                'feedback_state_duration' => 5,
                'reader_mode' => 'card_only',
            ],
            'serial' => [],
        ],
        'metadata' => [],
    ]);

    $event = Event::create([
        'access_card_id' => null,
        'access_area_id' => null,
        'access_lock_id' => null,
        'user_id' => null,
        'card_number' => null,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'origin_label' => $reader->name,
        'granted' => false,
        'status' => AccessEventStatus::INVALID_CARD->key(),
        'reason' => 'Invalid card.',
        'metadata' => ['raw_payload' => ['token' => 'hidden-value']],
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.access-events.show', $event));

    $response->assertOk();
    $response->assertSee('Metadata');
    $response->assertSee('Available');
    $response->assertDontSee('hidden-value');
    $response->assertDontSee('raw_payload');
});
