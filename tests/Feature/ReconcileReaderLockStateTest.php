<?php

use App\Enums\AccessControl\AccessEventStatus;
use App\Jobs\ProcessReaderEvent;
use App\Models\Access\Event;
use App\Models\Hardware\Reader;
use App\Services\AccessControl\AccessOutputOrchestrator;
use App\Services\AccessControl\AutolockSettingsResolver;
use App\Services\AccessControl\ExpectedLockStateStore;
use App\Services\AccessControl\ResolvedAccessBinding;
use App\Services\AccessControlMqttPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores expected lock power after a successful reader event', function () {
    $reader = Reader::create([
        'name' => 'Office Reader',
        'identifier' => 'office-reader',
        'config' => [
            'general' => [
                'autolock_enabled' => false,
                'autolock_duration' => 15,
            ],
        ],
        'metadata' => [],
    ]);

    $binding = new ResolvedAccessBinding('modbus', 'LockPower', '2', false);

    $outputOrchestrator = Mockery::mock(AccessOutputOrchestrator::class);
    $outputOrchestrator->shouldReceive('setLockState')
        ->once()
        ->withArgs(fn (Reader $subject, ?bool $targetLocked): bool => $subject->id === $reader->id && $targetLocked === true)
        ->andReturn([
            'binding' => $binding,
            'current_locked' => false,
            'new_locked' => true,
            'current_raw' => 0,
            'new_wire' => 1,
            'bindings' => [[
                'binding' => $binding,
                'current_locked' => false,
                'current_raw' => 0,
                'new_wire' => 1,
            ]],
        ]);

    $mqttPublisher = Mockery::mock(AccessControlMqttPublisher::class);
    $mqttPublisher->shouldReceive('publishReaderState')
        ->once()
        ->withArgs(fn (Reader $subject, ?int $knownLockPower): bool => $subject->id === $reader->id && $knownLockPower === 1);

    $job = new ProcessReaderEvent(null, $reader, 1, false, 'test');
    $job->handle($outputOrchestrator, app(AutolockSettingsResolver::class), $mqttPublisher, app(ExpectedLockStateStore::class));

    $reader->refresh();

    expect(data_get($reader->metadata, 'lock_state.expected_lock_power'))->toBe(1);
});

it('seeds expected lock power from the live lock state when none is stored', function () {
    $reader = Reader::create([
        'name' => 'Front Door Reader',
        'identifier' => 'front-door-reader',
        'config' => [],
        'metadata' => [],
    ]);

    $outputOrchestrator = Mockery::mock(AccessOutputOrchestrator::class);
    $outputOrchestrator->shouldReceive('readLockState')
        ->once()
        ->withArgs(fn (Reader $subject): bool => $subject->id === $reader->id)
        ->andReturn(true);
    $outputOrchestrator->shouldNotReceive('setLockState');

    $mqttPublisher = Mockery::mock(AccessControlMqttPublisher::class);
    $mqttPublisher->shouldNotReceive('publishReaderState');

    $this->app->instance(AccessOutputOrchestrator::class, $outputOrchestrator);
    $this->app->instance(AccessControlMqttPublisher::class, $mqttPublisher);

    $this->artisan('app:reconcile-reader-lock-state')->assertSuccessful();

    $reader->refresh();

    expect(data_get($reader->metadata, 'lock_state.expected_lock_power'))->toBe(1);
});

it('reconciles drift back to the stored expected lock state', function () {
    $reader = Reader::create([
        'name' => 'Office Reader',
        'identifier' => 'office-reader',
        'config' => [],
        'metadata' => [
            'lock_state' => [
                'expected_lock_power' => 0,
            ],
        ],
    ]);

    $outputOrchestrator = Mockery::mock(AccessOutputOrchestrator::class);
    $outputOrchestrator->shouldReceive('readLockState')
        ->once()
        ->withArgs(fn (Reader $subject): bool => $subject->id === $reader->id)
        ->andReturn(true);
    $outputOrchestrator->shouldReceive('setLockState')
        ->once()
        ->withArgs(fn (Reader $subject, ?bool $targetLocked): bool => $subject->id === $reader->id && $targetLocked === false)
        ->andReturn([
            'binding' => new ResolvedAccessBinding('modbus', 'LockPower', '2', false),
            'current_locked' => true,
            'new_locked' => false,
            'current_raw' => 1,
            'new_wire' => 0,
            'bindings' => [],
        ]);

    $mqttPublisher = Mockery::mock(AccessControlMqttPublisher::class);
    $mqttPublisher->shouldReceive('publishReaderState')
        ->once()
        ->withArgs(fn (Reader $subject, ?int $knownLockPower): bool => $subject->id === $reader->id && $knownLockPower === 0);

    $this->app->instance(AccessOutputOrchestrator::class, $outputOrchestrator);
    $this->app->instance(AccessControlMqttPublisher::class, $mqttPublisher);

    $this->artisan('app:reconcile-reader-lock-state')->assertSuccessful();

    expect(Event::query()->where('origin_type', 'lock')->where('status', AccessEventStatus::LOCK_STATE_RECONCILED->value)->exists())->toBeTrue();
});
