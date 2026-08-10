<?php

use App\Models\Access\Area;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('rebuilds supervisor config file from current readers and sources', function (): void {
    $area = Area::create([
        'name' => 'Plant',
        'identifier' => 'plant',
        'config' => [],
        'metadata' => [],
    ]);

    Reader::create([
        'name' => 'Plant Serial Reader',
        'identifier' => 'ttyUSB2',
        'area_id' => $area->id,
        'config' => ['serial' => ['device' => '/dev/ttyUSB2']],
        'metadata' => [],
    ]);

    Source::create([
        'name' => 'Plant Modbus',
        'identifier' => 'plant-modbus',
        'type' => 'modbus',
        'endpoint' => 'modbus://127.0.0.1:502',
        'enabled' => true,
        'config' => [],
        'metadata' => [],
    ]);

    $outputPath = storage_path('framework/testing/access-control-supervisor.conf');
    File::delete($outputPath);

    $this->artisan('app:rebuild-access-control-supervisor', [
        '--path' => $outputPath,
    ])->assertSuccessful();

    expect(File::exists($outputPath))->toBeTrue();

    $content = (string) File::get($outputPath);

    expect($content)->toContain('[program:access-control-queue]');
    expect($content)->toContain('[program:access-control-mqtt-monitor]');
    expect($content)->toContain('[program:access-control-serial-ttyUSB2]');
    expect($content)->toContain('[program:access-control-modbus-monitor]');
});
