<?php

use App\Enums\AccessControl\AccessEventStatus;
use App\Jobs\ProcessReaderEvent;
use App\Jobs\PublishReaderEvent;
use App\Jobs\PulseReaderFeedbackState;
use App\Models\Access\Card;
use App\Models\Access\Individual;
use App\Models\Hardware\Reader;
use App\Services\AccessControl\HandleAccessRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns a granted result and dispatches downstream jobs for a valid card', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Service User',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'SERVICE-CARD-1',
        'description' => 'Service test card',
        'active' => true,
    ]);

    $reader = Reader::create([
        'name' => 'Service Reader',
        'identifier' => 'service-reader',
        'config' => [],
        'metadata' => null,
    ]);

    $result = app(HandleAccessRequest::class)->validateCard($card->card_number, $reader->identifier, '127.0.0.1');

    expect($result->status)->toBe(AccessEventStatus::SUCCESS);
    expect($result->isGranted())->toBeTrue();
    expect($result->event->origin_type)->toBe('reader');
    expect($result->event->origin_id)->toBe($reader->id);
    expect($result->event->card_number)->toBe($card->card_number);

    Queue::assertPushed(ProcessReaderEvent::class, 1);
    Queue::assertPushed(PulseReaderFeedbackState::class, 1);
});

it('records a doorbell event and publishes it when the reader exists', function () {
    Queue::fake();

    $reader = Reader::create([
        'name' => 'Doorbell Reader',
        'identifier' => 'doorbell-reader',
        'config' => [],
        'metadata' => null,
    ]);

    $result = app(HandleAccessRequest::class)->recordDoorbellPress($reader->identifier, '127.0.0.1');

    expect($result->status)->toBe(AccessEventStatus::DOORBELL_PRESSED);
    expect($result->event->origin_type)->toBe('reader');
    expect($result->event->origin_id)->toBe($reader->id);
    expect($result->event->metadata)->toBe(['source' => 'physical_button']);

    Queue::assertPushed(PublishReaderEvent::class, 1);
});
