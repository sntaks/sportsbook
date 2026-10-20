<?php

use App\Models\Promotion;
use App\Models\User;

test('the promotions page lists running promotions only, in the order staff set', function () {
    Promotion::factory()->create(['title' => 'Second Offer', 'sort_order' => 2]);
    Promotion::factory()->create(['title' => 'First Offer', 'sort_order' => 1]);
    Promotion::factory()->draft()->create(['title' => 'Draft Offer']);
    Promotion::factory()->scheduled()->create(['title' => 'Future Offer']);
    Promotion::factory()->ended()->create(['title' => 'Old Offer']);

    $this->get(route('promotions'))
        ->assertOk()
        ->assertSeeInOrder(['First Offer', 'Second Offer'])
        ->assertDontSee('Draft Offer')
        ->assertDontSee('Future Offer')
        ->assertDontSee('Old Offer');
});

test('the invite card sends guests to sign up and players to their wallet', function () {
    $this->get(route('promotions'))->assertSee('Invite friends, get free bets')->assertSee(route('register'));

    $this->actingAs(User::factory()->create())->get(route('promotions'))->assertSee(route('wallet').'#invite');
});

test('with nothing running and invites switched off the page says so', function () {
    config(['sportsbook.promotions.referrals.enabled' => false]);

    $this->get(route('promotions'))->assertOk()->assertSee('No promotions running right now')->assertDontSee('Invite friends');
});

test('a running promotion has its own page with the body as HTML', function () {
    $promotion = Promotion::factory()->create(['body' => "## Terms\n\n- **Odds 1.50** or more"]);

    $this->get(route('promotions.show', $promotion))
        ->assertOk()
        ->assertSee($promotion->title)
        ->assertSee('<h2>Terms</h2>', false)
        ->assertSee('<strong>Odds 1.50</strong>', false);
});

test('HTML and script links typed into a promotion never reach the page', function () {
    $promotion = Promotion::factory()->create(['body' => "<script>alert('x')</script>\n\n<img src=x onerror=alert(1)>\n\n[Claim](javascript:alert(1))"]);

    $this->get(route('promotions.show', $promotion))
        ->assertOk()
        ->assertDontSee('<script>alert', false)
        ->assertDontSee('onerror', false)
        ->assertDontSee('javascript:alert', false);
});

test('draft, scheduled and ended promotions are not found', function (string $state) {
    $promotion = Promotion::factory()->{$state}()->create();

    $this->get(route('promotions.show', $promotion))->assertNotFound();
})->with(['draft', 'scheduled', 'ended']);
