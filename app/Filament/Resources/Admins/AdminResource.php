<?php

namespace App\Filament\Resources\Admins;

use App\Enums\AdminRole;
use App\Filament\Resources\Admins\Pages\ManageAdmins;
use App\Models\Admin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->options(AdminRole::options())
                    ->required()
                    ->disabled(fn (?Admin $record): bool => self::isCurrentAdmin($record))
                    ->helperText(fn (?Admin $record) => self::isCurrentAdmin($record) ? __('You cannot change your own role.') : null),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->rule(Password::default())
                    ->helperText(fn (string $operation) => $operation === 'edit' ? __('Leave blank to keep the current password.') : null),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true)
                    ->disabled(fn (?Admin $record): bool => self::isCurrentAdmin($record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminRole::tryFrom($state)?->label() ?? $state),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
                IconColumn::make('has_authenticator')
                    ->label(__('2FA set up'))
                    ->state(fn (Admin $record): bool => filled($record->getAppAuthenticationSecret()))
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label(__('Last sign-in'))
                    ->since()
                    ->placeholder(__('Never'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Admin $record): array => [
                        ...$data,
                        'role' => $record->getRoleNames()->first(),
                    ])
                    ->using(fn (Admin $record, array $data): Admin => self::saveAdmin($record, $data)),
                Action::make('resetAuthenticator')
                    ->label(__('Reset 2FA'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The admin will have to set up their authenticator app again the next time they sign in.'))
                    ->visible(fn (Admin $record): bool => filled($record->getAppAuthenticationSecret()))
                    ->action(function (Admin $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);

                        activity('admin')
                            ->performedOn($record)
                            ->event('authenticator-reset')
                            ->log('Authenticator app reset');

                        Notification::make()->title(__('2FA reset'))->success()->send();
                    }),
            ]);
    }

    /**
     * Create or update an admin. The role is applied through spatie/laravel-permission so the change reaches the
     * audit log, and a password change is logged without its value.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveAdmin(?Admin $admin, array $data): Admin
    {
        $attributes = Arr::except($data, 'role');

        if ($admin === null) {
            $admin = Admin::create($attributes);
        } else {
            $admin->update($attributes);

            if (filled($attributes['password'] ?? null)) {
                activity('admin')
                    ->performedOn($admin)
                    ->event('password-changed')
                    ->log('Password changed by another admin');
            }
        }

        if (filled($data['role'] ?? null) && ! $admin->hasExactRoles([$data['role']])) {
            $admin->syncRoles([$data['role']]);
        }

        return $admin;
    }

    /**
     * Whether the record is the signed-in admin, who may not demote or deactivate themselves.
     */
    private static function isCurrentAdmin(?Admin $record): bool
    {
        return $record !== null && $record->is(Filament::auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdmins::route('/'),
        ];
    }
}
