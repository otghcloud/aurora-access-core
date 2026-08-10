<?php

use App\Enums\AccessControl\AccessBindingActionKey;
use App\Jobs\PublishReaderState;
use App\Models\Access\Area;
use App\Models\Hardware\AdapterBinding;
use App\Models\Hardware\Lock;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('updates reader bindings from dedicated reader bindings screen', function (): void {
    Bus::fake([PublishReaderState::class]);

    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Reader Area',
        'identifier' => 'reader-area',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Reader Alpha',
        'identifier' => 'ttyUSB12',
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
        'name' => 'Reader Area Lock',
        'identifier' => 'reader-area-lock',
        'is_primary' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $source = Source::create([
        'name' => 'Reader Modbus',
        'identifier' => 'reader-modbus',
        'type' => 'modbus',
        'endpoint' => 'modbus://127.0.0.1:502',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $lockBinding = AdapterBinding::create([
        'source_id' => null,
        'direction' => 'output',
        'adapter_type' => 'edgelink',
        'target_type' => 'lock',
        'target_id' => $lock->id,
        'action_key' => AccessBindingActionKey::LOCK_POWER->value,
        'channel' => 'LockOnlyTag',
        'signal_reversed' => false,
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $response = $this->actingAs($admin)->put(route('admin.access-readers.bindings.update', $reader), [
        'inputs' => [
            [
                'source_id' => (string) $source->id,
                'adapter_type' => 'modbus',
                'action_key' => (string) AccessBindingActionKey::EXIT_REQUEST->value,
                'channel' => '1',
                'signal_reversed' => '0',
                'enabled' => '1',
                'config_json' => '{}',
            ],
        ],
        'outputs' => [
            [
                'source_id' => '',
                'adapter_type' => 'edgelink',
                'action_key' => (string) AccessBindingActionKey::READER_FEEDBACK_STATE->value,
                'channel' => 'ReaderFeedbackOnly',
                'signal_reversed' => '0',
                'enabled' => '1',
                'config_json' => '{}',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.access-readers.bindings.edit', $reader));

    AdapterBinding::query()
        ->where('target_type', 'reader')
        ->where('target_id', $reader->id)
        ->where('direction', 'input')
        ->where('action_key', AccessBindingActionKey::EXIT_REQUEST->value)
        ->firstOrFail();

    AdapterBinding::query()
        ->where('target_type', 'reader')
        ->where('target_id', $reader->id)
        ->where('direction', 'output')
        ->where('action_key', AccessBindingActionKey::READER_FEEDBACK_STATE->value)
        ->where('channel', 'ReaderFeedbackOnly')
        ->firstOrFail();

    $lockBinding->refresh();
    expect($lockBinding->deleted_at)->toBeNull();
    expect($lockBinding->channel)->toBe('LockOnlyTag');

    Bus::assertDispatched(PublishReaderState::class);
});
