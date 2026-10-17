<?php

namespace App\Filament\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication as BaseAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * Filament's authenticator-app provider with a working set-up QR code.
 *
 * Filament wraps the QR code in a `data:` URI whenever bacon/bacon-qr-code is installed without the imagick
 * extension, assuming the raw SVG of pragmarx/google2fa-qrcode v3. Version 4 already returns a complete data URI,
 * so the image arrived wrapped twice and never rendered. This wraps raw SVG only.
 */
class AppAuthentication extends BaseAppAuthentication
{
    public function generateQrCodeDataUri(#[SensitiveParameter] string $secret): string
    {
        /** @var HasAppAuthentication $user */
        $user = Filament::auth()->user();

        $qrCode = $this->google2FA->getQRCodeInline(
            $this->getBrandName(),
            $this->getHolderName($user),
            $secret,
        );

        if (str_starts_with($qrCode, 'data:')) {
            return $qrCode;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($qrCode);
    }
}
