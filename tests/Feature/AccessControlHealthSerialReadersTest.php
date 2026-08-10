<?php

use App\Models\Hardware\Reader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('includes serial reader checks in the health command output', function () {
    Reader::create([
        'name' => 'Serial Reader',
        'identifier' => 'ttyUSB9',
        'config' => [
            'general' => [
                'autolock_enabled' => false,
                'autolock_duration' => 0,
                'feedback_state_duration' => 5,
                'reader_mode' => 'card_only',
            ],
            'serial' => [
                'device' => '/dev/ttyUSB9',
                'baud_rate' => 9600,
                'timeout' => 1,
                'duplicate_window' => 2,
                'doorbell_duplicate_window' => 2,
                'keypad_timeout' => 3,
                'card_min_value' => 15,
                'doorbell_value' => 11,
            ],
            'edgelink' => [
                'tags' => ['lock_power' => null, 'feedback_state' => null],
                'signal_reversed' => ['lock_power' => false, 'feedback_state' => false],
            ],
        ],
        'metadata' => [],
    ]);

    Artisan::call('app:health-access-control', ['--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('Supervisor access-control-serial-ttyUSB9');
    expect($output)->toContain('Serial reader process ttyUSB9');
    expect($output)->toContain('Serial reader device ttyUSB9');
});
