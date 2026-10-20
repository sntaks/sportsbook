<?php

use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Models\Bet;
use App\Models\BetLeg;
use App\Models\User;
use Livewire\Livewire;

test('guests are sent to log in', function () {
    $this->get(route('my-bets'))->assertRedirect(route('login'));
});

test('lists the player\'s open bets with their legs, and nobody else\'s', function () {
    $player = User::factory()->create();
    $bet = Bet::factory()->for($player)->create(['reference' => 'OPENBET1', 'potential_payout' => 34_000]);
    BetLeg::factory()->for($bet)->create(['selection_name' => 'Arsenal', 'fixture_name' => 'Arsenal v Chelsea']);
    Bet::factory()->create(['reference' => 'NOTMINE1']);
    $this->actingAs($player);

    Livewire::test('pages::sportsbook.my-bets')
        ->assertSee('OPENBET1')
        ->assertSee('Arsenal v Chelsea')
        ->assertSee('KES 340.00')
        ->assertDontSee('NOTMINE1');
});

test('settled bets are on their own tab with what was paid', function () {
    $player = User::factory()->create();
    Bet::factory()->for($player)->create(['reference' => 'OPENBET1']);
    $won = Bet::factory()->for($player)->create(['reference' => 'WONBET01', 'status' => BetStatus::Won, 'payout' => 21_000, 'settled_at' => now()]);
    BetLeg::factory()->for($won)->create(['status' => BetLegStatus::Won]);
    $this->actingAs($player);

    Livewire::test('pages::sportsbook.my-bets')
        ->assertDontSee('WONBET01')
        ->call('show', 'settled')
        ->assertSee('WONBET01')
        ->assertSee('KES 210.00')
        ->assertDontSee('OPENBET1');
});

test('a free bet and a multibet bonus are marked on the bet', function () {
    $player = User::factory()->create();
    Bet::factory()->for($player)->create(['reference' => 'FREEBET1', 'free_bet_id' => freeBetFor($player)->id]);
    Bet::factory()->for($player)->create(['reference' => 'BONUSBT1', 'type' => BetType::Multi, 'bonus_percent' => 5, 'status' => BetStatus::Won, 'payout' => 95_078, 'bonus' => 2_478, 'settled_at' => now()]);
    $this->actingAs($player);

    Livewire::test('pages::sportsbook.my-bets')
        ->assertSee('Free bet stake')
        ->call('show', 'settled')
        ->assertSee('+5% bonus')
        ->assertSee('incl. KES 24.78 bonus');
});
