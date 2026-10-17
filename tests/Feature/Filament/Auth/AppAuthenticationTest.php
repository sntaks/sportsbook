<?php

use App\Enums\AdminRole;
use App\Filament\Auth\AppAuthentication;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

test('the set-up QR code is a single data URI that decodes straight to SVG', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');

    $dataUri = AppAuthentication::make()->generateQrCodeDataUri('JBSWY3DPEHPK3PXP');

    $decoded = base64_decode(Str::after($dataUri, 'data:image/svg+xml;base64,'), strict: true);
    expect($dataUri)->toStartWith('data:image/svg+xml;base64,')
        ->and($decoded)->not->toStartWith('data:')
        ->and($decoded)->toContain('<svg');
});

test('the admin panel uses the authenticator provider with the working QR code', function () {
    $providers = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();

    expect($providers)->toHaveCount(1)
        ->and(array_values($providers)[0])->toBeInstanceOf(AppAuthentication::class);
});
