<?php

use App\Models\Access\Area;
use App\Models\Hardware\Reader;
use App\Models\User;
use App\Services\AccessControl\LockStatusResolver;
use App\Services\AccessControlHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows configured locks as dashboard cards', function () {
    $admin = User::factory()->create();

    $area = Area::create([
        'name' => 'Front Door',
        'identifier' => 'front-door',
        'config' => [
            'locking' => [
                'autolock_enabled' => true,
                'autolock_duration' => 15,
            ],
        ],
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Front Door Reader',
        'identifier' => 'ttyUSB1',
        'area_id' => $area->id,
        'config' => [
            'general' => [
                'feedback_state_duration' => 5,
            ],
        ],
        'metadata' => [],
    ]);

    $health = Mockery::mock(AccessControlHealthService::class);
    $health->shouldReceive('getLastHealthStatus')->once()->andReturn([
        'generated_at' => now()->toIso8601String(),
        'checks' => [],
    ]);
    app()->instance(AccessControlHealthService::class, $health);

    $resolver = Mockery::mock(LockStatusResolver::class);
    $resolver->shouldReceive('resolve')->once()->with(Mockery::on(fn ($model) => $model->is($reader)))->andReturn([
        'state' => 'locked',
        'label' => 'Locked',
        'badge' => 'danger',
        'tag' => '1',
        'error' => null,
        'adapter_type' => 'modbus',
        'signal_reversed' => false,
    ]);
    app()->instance(LockStatusResolver::class, $resolver);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Configured Locks');
    $response->assertSee('wire:poll.3s', false);
    $response->assertSee('Front Door');
    $response->assertDontSee('Front Door Reader');
    $response->assertSee('Locked');
    $response->assertSee('Unlock');
    $response->assertSee('Enabled');
    $response->assertDontSee('ttyUSB1');
});
