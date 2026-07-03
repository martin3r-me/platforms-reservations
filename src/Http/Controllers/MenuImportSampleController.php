<?php

namespace Platform\Reservation\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert die Beispiel-CSV für den Artikel-Import zum Download.
 */
class MenuImportSampleController
{
    public function __invoke(): BinaryFileResponse
    {
        $path = __DIR__ . '/../../../resources/samples/artikel-import-vorlage.csv';

        return response()->download($path, 'artikel-import-vorlage.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
