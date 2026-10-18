<?php

namespace App\Filament\Pages;

use App\Enums\AdminPermission;
use App\Filament\Pages\Risk\MatchExposure;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Where the book's money is riding: open bets by match and what each pick would cost.
 */
class Risk extends Page
{
    protected string $view = 'filament.pages.risk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Betting';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(AdminPermission::ViewBets->value) ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [MatchExposure::class];
    }
}
