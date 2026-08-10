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

it('updates lock bindings from the lock bindings screen', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Main Entrance',
        'identifier' => 'main-entrance',
        'metadata' => [],
    ]);

    $lock = Lock::create([
        'area_id' => $area->id,
        'name' => 'Main Door Lock',
        'identifier' => 'main-door-lock',
        'is_primary' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $source = Source::create([
        'name' => 'Main Modbus',
        'identifier' => 'main-modbus',
        'type' => 'modbus',
        'endpoint' => 'modbus://127.0.0.1:502',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $response = $this->actingAs($admin)->put(route('admin.access-locks.bindings.update', $lock), [
        'outputs' => [
            [
                'source_id' => (string) $source->id,
                'adapter_type' => 'modbus',
                'action_key' => (string) AccessBindingActionKey::LOCK_POWER->value,
                'channel' => '3',
                'signal_reversed' => '0',
                'enabled' => '1',
                'config_json' => '{"modbus":{"coil_start_address":0}}',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.access-locks.show', $lock));

    $binding = AdapterBinding::query()
        ->where('target_type', 'lock')
        ->where('target_id', $lock->id)
        ->where('action_key', AccessBindingActionKey::LOCK_POWER->value)
        ->firstOrFail();

    expect($binding->adapter_type)->toBe('modbus');
    expect($binding->channel)->toBe('3');
    expect($binding->source_id)->toBe($source->id);
});

it('does not delete lock bindings when updating reader-owned bindings', function (): void {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Lab',
        'identifier' => 'lab',
        'metadata' => [],
    ]);

    $lock = Lock::create([
        'area_id' => $area->id,
        'name' => 'Lab Lock',
        'identifier' => 'lab-lock',
        'is_primary' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Lab Reader',
        'identifier' => 'ttyUSB8',
        'area_id' => $area->id,
        'config' => [
            'general' => [
                'autolock_enabled' => true,
                'autolock_duration' => 10,
                'feedback_state_duration' => 5,
                'reader_mode' => 'card_only',
            ],
            'serial' => [
                'device' => '/dev/ttyUSB8',
                'baud_rate' => 9600,
                'timeout' => 1,
                'duplicate_window' => 2,
                'doorbell_duplicate_window' => 2,
                'keypad_timeout' => 3,
                'card_min_value' => 15,
                'doorbell_value' => 11,
            ],
        ],
        'metadata' => [],
    ]);

    $lockBinding = AdapterBinding::create([
        'source_id' => null,
        'direction' => 'output',
        'adapter_type' => 'edgelink',
        'target_type' => 'lock',
        'target_id' => $lock->id,
        'action_key' => AccessBindingActionKey::LOCK_POWER->value,
        'channel' => 'LockPowerTag',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $this->actingAs($admin)->put(route('admin.access-readers.update', $reader), [
        'name' => 'Lab Reader Updated',
        'identifier' => 'ttyUSB8',
        'area_id' => $area->id,
        'general_autolock_enabled' => '1',
        'general_autolock_duration' => '12',
        'general_feedback_state_duration' => '5',
        'general_reader_mode' => 'card_only',
        'wiegand_device' => '/dev/ttyUSB8',
        'wiegand_baud_rate' => '9600',
        'wiegand_timeout' => '1',
        'wiegand_duplicate_window' => '2',
        'wiegand_doorbell_duplicate_window' => '2',
        'wiegand_keypad_timeout' => '3',
        'wiegand_card_min_value' => '15',
        'wiegand_doorbell_value' => '11',
        'outputs' => [
            [
                'source_id' => '',
                'adapter_type' => 'edgelink',
                'action_key' => (string) AccessBindingActionKey::READER_FEEDBACK_STATE->value,
                'channel' => 'ReaderFeedbackTag',
                'signal_reversed' => '0',
                'enabled' => '1',
                'config_json' => '{}',
            ],
        ],
    ])->assertRedirect(route('admin.access-readers.index'));

    $lockBinding->refresh();

    expect($lockBinding->deleted_at)->toBeNull();
    expect($lockBinding->channel)->toBe('LockPowerTag');
});
