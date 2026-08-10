<?php

use App\Enums\AccessControl\AccessBindingActionKey;
use App\Models\Access\Area;
use App\Models\Hardware\AdapterBinding;
use App\Models\Hardware\Lock;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders area bindings workspace with reader and lock entrypoints', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Area One',
        'identifier' => 'area-one',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Reader One',
        'identifier' => 'ttyUSB10',
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
        'name' => 'Lock One',
        'identifier' => 'lock-one',
        'is_primary' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $source = Source::create([
        'name' => 'Modbus A',
        'identifier' => 'modbus-a',
        'type' => 'modbus',
        'endpoint' => 'modbus://127.0.0.1:502',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    AdapterBinding::create([
        'source_id' => $source->id,
        'direction' => 'output',
        'adapter_type' => 'modbus',
        'target_type' => 'lock',
        'target_id' => $lock->id,
        'action_key' => AccessBindingActionKey::LOCK_POWER->value,
        'channel' => '5',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    AdapterBinding::create([
        'source_id' => null,
        'direction' => 'output',
        'adapter_type' => 'edgelink',
        'target_type' => 'reader',
        'target_id' => $reader->id,
        'action_key' => AccessBindingActionKey::READER_FEEDBACK_STATE->value,
        'channel' => 'ReaderFeedbackTag',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.access-areas.bindings', $area));

    $response->assertOk();
    $response->assertSee('Area Bindings Workspace');
    $response->assertSee('Reader One');
    $response->assertSee('Lock One');
    $response->assertSee(route('admin.access-readers.bindings.edit', $reader), false);
    $response->assertSee('reader:'.$reader->id, false);
    $response->assertSee('lock:'.$lock->id, false);
});

it('filters bindings index by target id', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Area Two',
        'identifier' => 'area-two',
        'metadata' => [],
    ]);

    $lockA = Lock::create([
        'area_id' => $area->id,
        'name' => 'Lock A',
        'identifier' => 'lock-a',
        'is_primary' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $lockB = Lock::create([
        'area_id' => $area->id,
        'name' => 'Lock B',
        'identifier' => 'lock-b',
        'is_primary' => false,
        'config' => [],
        'metadata' => [],
    ]);

    AdapterBinding::create([
        'source_id' => null,
        'direction' => 'output',
        'adapter_type' => 'edgelink',
        'target_type' => 'lock',
        'target_id' => $lockA->id,
        'action_key' => AccessBindingActionKey::LOCK_POWER->value,
        'channel' => 'LockATag',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    AdapterBinding::create([
        'source_id' => null,
        'direction' => 'output',
        'adapter_type' => 'edgelink',
        'target_type' => 'lock',
        'target_id' => $lockB->id,
        'action_key' => AccessBindingActionKey::LOCK_POWER->value,
        'channel' => 'LockBTag',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.access-bindings.index', [
        'target_type' => 'lock',
        'target_id' => $lockA->id,
    ]));

    $response->assertOk();
    $response->assertSee('LockATag');
    $response->assertDontSee('LockBTag');
});
