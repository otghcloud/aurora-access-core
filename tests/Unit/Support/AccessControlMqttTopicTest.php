<?php

use App\Models\Access\Area;
use App\Models\Hardware\Reader;
use App\Support\AccessControlMqttTopic;
use Tests\TestCase;

uses(TestCase::class);

it('builds area-based topics for readers assigned to an area', function (): void {
    $area = new Area([
        'name' => 'Main Entrance',
        'identifier' => 'main-entrance',
    ]);
    $area->id = 7;

    $reader = new Reader([
        'name' => 'Reader A',
        'identifier' => 'ttyUSB0',
    ]);
    $reader->id = 99;
    $reader->setRelation('area', $area);
    $prefix = 'access_control';

    expect(AccessControlMqttTopic::readerSlug($reader))->toBe('main-entrance');
    expect(AccessControlMqttTopic::commandTopic($reader))->toBe($prefix.'/main-entrance/cmd');
    expect(AccessControlMqttTopic::stateTopic($reader))->toBe($prefix.'/main-entrance/state');
    expect(AccessControlMqttTopic::eventsTopic($reader))->toBe($prefix.'/main-entrance/events');
});

it('requires an area assignment when building reader topics', function (): void {
    $reader = new Reader([
        'name' => 'Standalone Reader',
        'identifier' => 'ttyUSB2',
    ]);
    $reader->id = 5;
    $reader->setRelation('area', null);

    expect(fn () => AccessControlMqttTopic::readerSlug($reader))
        ->toThrow(LogicException::class);
});
