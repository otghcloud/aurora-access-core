<?php

use App\Enums\AccessControl\AccessEventStatus;
use App\Models\Access\Card;
use App\Models\Access\Event;
use App\Models\Access\Individual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows card details and related events', function (): void {
    $admin = User::factory()->create();

    $accessUser = Individual::create([
        'name' => 'Lee Main Keys',
        'email' => 'lee@example.test',
        'active' => true,
    ]);

    $card = Card::create([
        'user_id' => $accessUser->id,
        'card_number' => 'TEST-CARD-123',
        'active' => true,
        'description' => 'Lee Main Keys',
    ]);

    Event::create([
        'access_card_id' => $card->id,
        'user_id' => $accessUser->id,
        'card_number' => $card->card_number,
        'granted' => true,
        'status' => AccessEventStatus::SUCCESS->value,
        'reason' => 'Credential accepted.',
        'origin_type' => 'reader',
        'origin_id' => 1,
        'origin_label' => 'Front Door Reader',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.access-cards.show', $card))
        ->assertOk()
        ->assertSee('Access Card')
        ->assertSee('Lee Main Keys')
        ->assertSee('TEST-CARD-123')
        ->assertSee('Credential Accepted');
});
