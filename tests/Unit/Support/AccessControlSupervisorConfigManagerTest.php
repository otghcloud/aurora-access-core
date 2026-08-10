<?php

use App\Models\Access\Area;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use App\Services\Supervisor\AccessControlSupervisorConfigManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders required static programs and dynamic serial/modbus programs', function (): void {
    $area = Area::create([
        'name' => 'Main Area',
        'identifier' => 'main-area',
        'config' => [],
        'metadata' => [],
    ]);

    Reader::create([
        'name' => 'Serial Reader',
        'identifier' => 'ttyUSB0',
        'area_id' => $area->id,
        'config' => ['serial' => ['device' => '/dev/ttyUSB0']],
        'metadata' => [],
    ]);

    Reader::create([
        'name' => 'API Reader',
        'identifier' => 'api-reader',
        'area_id' => $area->id,
        'config' => [],
        'metadata' => [],
    ]);

    Source::create([
        'name' => 'Modbus Source',
        'identifier' => 'modbus-main',
        'type' => 'modbus',
        'endpoint' => 'modbus://127.0.0.1:502',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $content = app(AccessControlSupervisorConfigManager::class)->render();

    expect($content)->toContain('[program:access-control-queue]');
    expect($content)->toContain('[program:access-control-mqtt-monitor]');
    expect($content)->toContain('[program:access-control-serial-ttyUSB0]');
    expect($content)->toContain('[program:access-control-modbus-monitor]');
    expect($content)->not->toContain('[program:access-control-opc-monitor-queue]');
    expect($content)->not->toContain('[program:access-control-serial-api-reader]');
});

it('includes opc monitor queue when opc sources exist', function (): void {
    Source::create([
        'name' => 'OPC Source',
        'identifier' => 'opc-main',
        'type' => 'opcua',
        'endpoint' => 'opc.tcp://127.0.0.1:4840',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $content = app(AccessControlSupervisorConfigManager::class)->render();

    expect($content)->toContain('[program:access-control-opc-monitor-queue]');
});
