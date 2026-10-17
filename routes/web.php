<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::sportsbook.home')->name('home');
Route::livewire('live', 'pages::sportsbook.live')->name('live');
Route::livewire('jackpot', 'pages::sportsbook.jackpot')->name('jackpot');
Route::livewire('promotions', 'pages::sportsbook.promotions')->name('promotions');
Route::livewire('search', 'pages::sportsbook.search')->name('search');
Route::livewire('competitions/{competition}', 'pages::sportsbook.competition')->name('competitions.show');
Route::livewire('matches/{fixture}', 'pages::sportsbook.match')->whereNumber('fixture')->name('matches.show');

Route::view('virtuals', 'sportsbook.coming-soon', [
    'title' => 'Virtuals',
    'icon' => 'cpu-chip',
    'description' => 'Virtual football with a new round every few minutes is on its way.',
])->name('virtuals');

Route::view('crash', 'sportsbook.coming-soon', [
    'title' => 'Crash',
    'icon' => 'rocket-launch',
    'description' => 'Cash out before the multiplier crashes. This game is on its way.',
])->name('crash');

Route::view('terms', 'sportsbook.legal', ['title' => 'Terms & Conditions'])->name('terms');
Route::view('privacy', 'sportsbook.legal', ['title' => 'Privacy Policy'])->name('privacy');
Route::view('responsible-gambling', 'sportsbook.legal', ['title' => 'Responsible Gambling'])->name('responsible-gambling');

Route::middleware(['auth'])->group(function () {
    Route::livewire('my-bets', 'pages::sportsbook.my-bets')->name('my-bets');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
