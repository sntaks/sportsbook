<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Models\Promotion;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('Image'))
                    ->disk('public')
                    ->imageHeight(40),
                TextColumn::make('title')
                    ->description(fn (Promotion $record): string => '/promotions/'.$record->slug)
                    ->searchable(),
                TextColumn::make('state')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (Promotion $record): string => self::stateLabel($record->state()))
                    ->color(fn (Promotion $record): string => match ($record->state()) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')
                    ->label(__('Starts (EAT)'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('Ends (EAT)'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->placeholder(__('No end'))
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Open on site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Promotion $record): string => route('promotions.show', $record), shouldOpenInNewTab: true)
                    ->visible(fn (Promotion $record): bool => $record->isLive()),
                EditAction::make(),
            ]);
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'draft' => __('Draft'),
            'scheduled' => __('Scheduled'),
            'ended' => __('Ended'),
            default => __('Live'),
        };
    }
}
