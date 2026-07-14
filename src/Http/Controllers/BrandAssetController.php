<?php

namespace Platform\Reservation\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert gebündelte Marken-Assets (z. B. das Culinaria-Logo) aus dem Modul –
 * ohne separates Asset-Publishing. Öffentlich (Gast-Seite).
 */
class BrandAssetController
{
    public function logo(): BinaryFileResponse
    {
        $path = __DIR__ . '/../../../resources/brand/culinaria-logo.svg';

        return response()->file($path, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
