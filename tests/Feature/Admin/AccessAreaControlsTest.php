<?php

use App\Jobs\ProcessReaderEvent;
use App\Jobs\PublishReaderState;
use App\Models\Access\Area;
use App\Models\Access\Event;
use App\Models\Hardware\Reader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('requires authentication for area control endpoints', function (): void {
    $area = Area::create([
        'name' => 'Plant North',
        'identifier' => 'plant-north',
        'metadata' => [],
    ]);

    $this->post(route('admin.access-areas.lock', $area))->assertRedirect(route('login'));
    $this->post(route('admin.access-areas.unlock', $area))->assertRedirect(route('login'));
    $this->post(route('admin.access-areas.autolock', $area), [
        'autolock_enabled' => 1,
        'autolock_duration' => 20,
    ])->assertRedirect(route('login'));
});

it('dispatches lock and unlock commands from area controls', function (): void {
    Bus::fake([ProcessReaderEvent::class, PublishReaderState::class]);

    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Plant South',
        'identifier' => 'plant-south',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'South Reader',
        'identifier' => 'ttyUSB4',
        'area_id' => $area->id,
        'config' => [
            'general' => [
                'autolock_enabled' => true,
                'autolock_duration' => 15,
            ],
            'serial' => [],
        ],
        'metadata' => [],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.access-areas.lock', $area))
        ->assertRedirect(route('admin.access-areas.index'));

    Bus::assertDispatched(ProcessReaderEvent::class, function (ProcessReaderEvent $job) use ($reader): bool {
        return $job->accessReader->is($reader)
            && $job->targetValue === 1
            && $job->allowAutoRelock === false
            && $job->eventSource === 'admin_area_control';
    });

    $lockEvent = Event::query()
        ->where('access_area_id', $area->id)
        ->latest('id')
        ->firstOrFail();

    expect($lockEvent->status)->toBe('admin_lock_requested');
    expect($lockEvent->origin_type)->toBe('lock');

    $this->actingAs($admin)
        ->post(route('admin.access-areas.unlock', $area))
        ->assertRedirect(route('admin.access-areas.index'));

    Bus::assertDispatched(ProcessReaderEvent::class, function (ProcessReaderEvent $job) use ($reader): bool {
        return $job->accessReader->is($reader)
            && $job->targetValue === 0
            && $job->allowAutoRelock === true
            && $job->eventSource === 'admin_area_control';
    });

    $unlockEvent = Event::query()
        ->where('access_area_id', $area->id)
        ->latest('id')
        ->firstOrFail();

    expect($unlockEvent->status)->toBe('admin_unlock_requested');
    expect($unlockEvent->origin_type)->toBe('lock');
});

it('updates autolock across area readers and dispatches mqtt state jobs', function (): void {
    Bus::fake([PublishReaderState::class]);

    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Warehouse',
        'identifier' => 'warehouse',
        'metadata' => [],
    ]);

    $readerA = Reader::create([
        'name' => 'Warehouse Reader A',
        'identifier' => 'ttyUSB6',
        'area_id' => $area->id,
        'config' => ['general' => ['autolock_enabled' => false, 'autolock_duration' => 5]],
        'metadata' => [],
    ]);

    $readerB = Reader::create([
        'name' => 'Warehouse Reader B',
        'identifier' => 'ttyUSB7',
        'area_id' => $area->id,
        'config' => ['general' => ['autolock_enabled' => false, 'autolock_duration' => 5]],
        'metadata' => [],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.access-areas.autolock', $area), [
            'autolock_enabled' => 1,
            'autolock_duration' => 22,
        ])
        ->assertRedirect(route('admin.access-areas.index'));

    expect((bool) data_get($area->fresh()->config, 'locking.autolock_enabled'))->toBeTrue();
    expect((int) data_get($area->fresh()->config, 'locking.autolock_duration'))->toBe(22);

    Bus::assertDispatched(PublishReaderState::class, 2);

    $autolockEvent = Event::query()
        ->where('access_area_id', $area->id)
        ->latest('id')
        ->firstOrFail();

    expect($autolockEvent->status)->toBe('admin_autolock_updated');
    expect($autolockEvent->origin_type)->toBe('area');
});
