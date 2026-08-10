<?php

use App\Enums\AccessControl\AccessEventStatus;
use App\Jobs\ProcessReaderEvent;
use App\Models\Access\Area;
use App\Models\Access\AreaPermission;
use App\Models\Access\Card;
use App\Models\Access\Individual;
use App\Models\Hardware\Reader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('records a success event and dispatches the reader job', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Alice Carter',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-1001',
        'description' => 'Main office card',
        'active' => true,
    ]);

    $reader = Reader::create([
        'name' => 'Front Door',
        'identifier' => 'reader-front-door',
        'config' => [],
        'metadata' => null,
    ]);

    $response = $this->postJson('/validate', [
        'card' => $card->card_number,
        'reader' => $reader->identifier,
    ]);

    $response->assertStatus(200)->assertJson(['message' => 'Valid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => $card->id,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'user_id' => $accessUser->id,
        'card_number' => $card->card_number,
        'granted' => 1,
        'status' => AccessEventStatus::SUCCESS->value,
    ]);

    Queue::assertPushed(ProcessReaderEvent::class, 1);
});

it('records invalid card attempts and does not dispatch the reader job', function () {
    Queue::fake();

    $reader = Reader::create([
        'name' => 'Front Door',
        'identifier' => 'reader-front-door',
        'config' => [],
        'metadata' => null,
    ]);

    $response = $this->postJson('/validate', [
        'card' => 'MISSING-CARD',
        'reader' => $reader->identifier,
    ]);

    $response->assertStatus(403)->assertJson(['message' => 'Invalid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => null,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'granted' => 0,
        'status' => AccessEventStatus::INVALID_CARD->value,
        'card_number' => 'MISSING-CARD',
    ]);

    Queue::assertNotPushed(ProcessReaderEvent::class);
});

it('records inactive card attempts and does not dispatch the reader job', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Bob Young',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-2002',
        'description' => 'Inactive card',
        'active' => false,
    ]);

    $reader = Reader::create([
        'name' => 'Front Door',
        'identifier' => 'reader-front-door',
        'config' => [],
        'metadata' => null,
    ]);

    $response = $this->postJson('/validate', [
        'card' => $card->card_number,
        'reader' => $reader->identifier,
    ]);

    $response->assertStatus(403)->assertJson(['message' => 'Invalid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => $card->id,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'user_id' => $accessUser->id,
        'granted' => 0,
        'status' => AccessEventStatus::INACTIVE_CARD->value,
    ]);

    Queue::assertNotPushed(ProcessReaderEvent::class);
});

it('records invalid reader attempts and does not dispatch the reader job', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Cara Lane',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-3003',
        'description' => 'Main office card',
        'active' => true,
    ]);

    $response = $this->postJson('/validate', [
        'card' => $card->card_number,
        'reader' => 'missing-reader',
    ]);

    $response->assertStatus(403)->assertJson(['message' => 'Invalid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => $card->id,
        'origin_type' => 'reader',
        'origin_id' => null,
        'origin_label' => 'missing-reader',
        'user_id' => $accessUser->id,
        'granted' => 0,
        'status' => AccessEventStatus::INVALID_READER->value,
    ]);

    Queue::assertNotPushed(ProcessReaderEvent::class);
});

it('supports one access user with multiple access cards', function () {
    $accessUser = Individual::create([
        'name' => 'Dana Cole',
    ]);

    Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-4004',
        'description' => 'Day pass',
        'active' => true,
    ]);

    Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-4005',
        'description' => 'Night pass',
        'active' => true,
    ]);

    expect($accessUser->cards()->count())->toBe(2);
});

it('denies access when user has scoped permissions but none for reader area', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Area Scoped User',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-5001',
        'description' => 'Scoped card',
        'active' => true,
    ]);

    $allowedRoom = Area::create([
        'name' => 'Allowed Area',
        'identifier' => 'allowed-area',
        'metadata' => [],
    ]);

    $targetRoom = Area::create([
        'name' => 'Restricted Area',
        'identifier' => 'restricted-area',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Restricted Reader',
        'identifier' => 'reader-restricted',
        'area_id' => $targetRoom->id,
        'config' => [],
        'metadata' => null,
    ]);

    AreaPermission::create([
        'individual_id' => $accessUser->id,
        'area_id' => $allowedRoom->id,
        'permission' => 'allow',
        'metadata' => [],
    ]);

    $response = $this->postJson('/validate', [
        'card' => $card->card_number,
        'reader' => $reader->identifier,
    ]);

    $response->assertStatus(403)->assertJson(['message' => 'Invalid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => $card->id,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'status' => AccessEventStatus::AREA_NOT_PERMITTED->value,
        'granted' => 0,
    ]);

    Queue::assertNotPushed(ProcessReaderEvent::class);
});

it('grants access when allow permission exists for reader area', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Allowed User',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-5002',
        'description' => 'Allowed card',
        'active' => true,
    ]);

    $area = Area::create([
        'name' => 'Lab Area',
        'identifier' => 'lab-area',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Lab Reader',
        'identifier' => 'reader-lab',
        'area_id' => $area->id,
        'config' => [],
        'metadata' => null,
    ]);

    AreaPermission::create([
        'individual_id' => $accessUser->id,
        'area_id' => $area->id,
        'permission' => 'allow',
        'metadata' => [],
    ]);

    $response = $this->postJson('/validate', [
        'card' => $card->card_number,
        'reader' => $reader->identifier,
    ]);

    $response->assertStatus(200)->assertJson(['message' => 'Valid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => $card->id,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'status' => AccessEventStatus::SUCCESS->value,
        'granted' => 1,
    ]);

    Queue::assertPushed(ProcessReaderEvent::class, 1);
});

it('denies access when area permission explicitly denies', function () {
    Queue::fake();

    $accessUser = Individual::create([
        'name' => 'Denied User',
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'CARD-5003',
        'description' => 'Denied card',
        'active' => true,
    ]);

    $area = Area::create([
        'name' => 'Finance Area',
        'identifier' => 'finance-area',
        'metadata' => [],
    ]);

    $reader = Reader::create([
        'name' => 'Finance Reader',
        'identifier' => 'reader-finance',
        'area_id' => $area->id,
        'config' => [],
        'metadata' => null,
    ]);

    AreaPermission::create([
        'individual_id' => $accessUser->id,
        'area_id' => $area->id,
        'permission' => 'deny',
        'metadata' => [],
    ]);

    $response = $this->postJson('/validate', [
        'card' => $card->card_number,
        'reader' => $reader->identifier,
    ]);

    $response->assertStatus(403)->assertJson(['message' => 'Invalid']);

    $this->assertDatabaseHas('events', [
        'access_card_id' => $card->id,
        'origin_type' => 'reader',
        'origin_id' => $reader->id,
        'status' => AccessEventStatus::AREA_DENIED->value,
        'granted' => 0,
    ]);

    Queue::assertNotPushed(ProcessReaderEvent::class);
});
