<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

/**
 * Example promotion pages for a local site, describing offers the app really runs. Run with
 * `php artisan db:seed --class=PromotionSeeder`; safe to run again.
 */
class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $promotions = [
            [
                'slug' => 'multibet-bonus',
                'title' => 'Multibet bonus up to 50%',
                'summary' => 'Back 3 or more picks at odds 1.30+ on one bet and win up to 50% extra on your profit.',
                'body' => <<<'MD'
                ## How it works

                Put 3 or more picks priced **1.30 or more** on one bet. If it wins, we add a bonus to your profit (your winnings minus your stake).

                | Picks at 1.30+ | Bonus |
                |---|---|
                | 3 | 3% |
                | 4 | 5% |
                | 5 | 7% |
                | 6 | 10% |
                | 8 | 15% |
                | 10 | 20% |
                | 15 | 35% |
                | 20 or more | 50% |

                ## Terms

                - Only winning picks at 1.30 or more count. A void pick does not.
                - The bonus is never more than your bet qualified for when you placed it.
                - Not paid on free bets. Winnings tax applies to the bonus.
                MD,
                'sort_order' => 1,
            ],
            [
                'slug' => 'weekly-jackpot',
                'title' => 'Weekly Mega Jackpot',
                'summary' => 'Predict 17 matches for KES 99. Get them all right to share KES 1,000,000.',
                'body' => <<<'MD'
                ## How to play

                Pick home win, draw or away win (1, X or 2) for every match on the [jackpot page](/jackpot). Each entry costs **KES 99**.

                ## Prizes

                - 17 right: KES 1,000,000, shared by every entry with 17 right
                - 16 right: KES 100,000, shared
                - 15 right: KES 20,000, shared

                ## Terms

                - Entries close when the first match kicks off.
                - A postponed or void match counts as right for everyone. If more than 3 matches are void, the jackpot is cancelled and every entry refunded.
                MD,
                'sort_order' => 2,
            ],
            [
                'slug' => 'invite-friends',
                'title' => 'Invite friends, get KES 100 free bets',
                'summary' => 'Share your invite link. Get a KES 100 free bet for every friend who joins, deposits and bets.',
                'body' => <<<'MD'
                ## How it works

                1. Find your invite link on your [wallet page](/account/wallet#invite).
                2. Your friend signs up with it, or types your invite code when signing up.
                3. Within 30 days your friend deposits at least KES 100 and stakes at least KES 200 of their own money.
                4. You get a **KES 100 free bet** (odds 1.50 or more, use it within 7 days).

                ## Terms

                - Up to 10 rewards a calendar month.
                - Free bets and refunded bets do not count towards your friend's KES 200.
                - No reward is given while your account is suspended or on a break from betting.
                MD,
                'sort_order' => 3,
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::query()->updateOrCreate(['slug' => $promotion['slug']], [
                ...$promotion,
                'starts_at' => now()->startOfDay(),
                'is_published' => true,
            ]);
        }
    }
}
