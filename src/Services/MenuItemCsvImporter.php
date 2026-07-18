<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\DB;
use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;

/**
 * CSV-Import für Artikel (Dry-Run-Vorschau + Import).
 *
 * Erwartete Spalten (Header-Zeile, Reihenfolge egal):
 * name; beschreibung; portionsgroesse; kategorie; preis; mwst; allergene; zusatzstoffe;
 * vegetarisch; vegan; alkohol; verfuegbar
 *
 * Allergene als Buchstaben-Codes ("A,C,G"), Zusatzstoffe als Nummern ("1,2").
 * Unbekannte Codes werden als Warnung gemeldet und übersprungen (keine
 * Falschdaten). Fehlende Kategorien werden beim Import angelegt.
 */
class MenuItemCsvImporter
{
    public const STATUS_OK      = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR   = 'error';

    protected const KNOWN_COLUMNS = [
        'name', 'beschreibung', 'portionsgroesse', 'kategorie', 'preis', 'mwst',
        'allergene', 'zusatzstoffe', 'vegetarisch', 'vegan', 'alkohol', 'verfuegbar',
    ];

    /**
     * CSV parsen und jede Zeile validieren – schreibt nichts (Dry-Run).
     *
     * @return array{rows: array, errors: array}
     */
    public function parse(string $content, int $teamId): array
    {
        $content = $this->normalizeEncoding($content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            return ['rows' => [], 'errors' => ['Die Datei enthält keine Datenzeilen.']];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $headers = array_map(
            fn ($h) => mb_strtolower(trim($h)),
            str_getcsv($lines[0], $delimiter)
        );

        if (!in_array('name', $headers, true)) {
            return ['rows' => [], 'errors' => ['Pflichtspalte „name“ fehlt in der Kopfzeile. Gefunden: ' . implode(', ', $headers)]];
        }

        $allergenCodes = Allergen::whereNotNull('code')->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [mb_strtoupper($code) => $id])->all();
        $additiveCodes = Additive::pluck('id', 'code')->all();
        $existingNames = MenuItem::forTeam($teamId)->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))->flip()->all();

        $rows = [];

        foreach (array_slice($lines, 1) as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $data = [];
            foreach ($headers as $i => $header) {
                if (in_array($header, self::KNOWN_COLUMNS, true)) {
                    $data[$header] = trim($values[$i] ?? '');
                }
            }

            $rows[] = $this->validateRow($data, $lineNumber + 2, $allergenCodes, $additiveCodes, $existingNames);
        }

        return ['rows' => $rows, 'errors' => []];
    }

    /**
     * Geparste Zeilen importieren (nur Status ok/warning, keine Duplikate).
     *
     * @return array{created: int, skipped: int}
     */
    public function import(array $rows, int $teamId): array
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $teamId, &$created, &$skipped) {
            foreach ($rows as $row) {
                if ($row['status'] === self::STATUS_ERROR || $row['duplicate']) {
                    $skipped++;
                    continue;
                }

                $category = MenuCategory::firstOrCreate(
                    ['team_id' => $teamId, 'name' => $row['category']],
                );

                $item = MenuItem::create([
                    'team_id'         => $teamId,
                    'category_id'     => $category->id,
                    'name'            => $row['name'],
                    'description'     => $row['description'] ?: null,
                    'portion_size'    => $row['portion_size'] ?: null,
                    'price'           => $row['price'],
                    'tax_rate'        => $row['tax_rate'],
                    'available'       => $row['available'],
                    'is_vegetarian'   => $row['is_vegetarian'],
                    'is_vegan'        => $row['is_vegan'],
                    'is_alcoholic'    => $row['is_alcoholic'],
                    'approval_status' => MenuItem::APPROVAL_DRAFT,
                ]);

                $item->allergens()->sync($row['allergen_ids']);
                $item->additives()->sync($row['additive_ids']);

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    protected function validateRow(
        array $data,
        int $lineNumber,
        array $allergenCodes,
        array $additiveCodes,
        array $existingNames,
    ): array {
        $messages = [];
        $status = self::STATUS_OK;
        $warn = function (string $message) use (&$messages, &$status) {
            $messages[] = $message;
            if ($status !== self::STATUS_ERROR) {
                $status = self::STATUS_WARNING;
            }
        };

        $name = $data['name'] ?? '';
        if ($name === '') {
            $messages[] = 'Name fehlt – Zeile wird nicht importiert.';
            $status = self::STATUS_ERROR;
        }

        $duplicate = $name !== '' && isset($existingNames[mb_strtolower($name)]);
        if ($duplicate) {
            $warn('Artikel existiert bereits – Zeile wird übersprungen.');
        }

        // Preis (deutsches Komma erlaubt)
        $price = str_replace(['€', ' '], '', $data['preis'] ?? '');
        $price = str_replace(',', '.', $price);
        if ($price === '') {
            $price = '0.00';
            $warn('Kein Preis angegeben – 0,00 € übernommen.');
        } elseif (!is_numeric($price)) {
            $warn("Preis „{$data['preis']}“ nicht lesbar – 0,00 € übernommen.");
            $price = '0.00';
        }

        // MwSt normalisieren ("7", "7%", "7,00" → "7.00")
        $taxRaw = str_replace(['%', ' '], '', $data['mwst'] ?? '');
        $taxRaw = str_replace(',', '.', $taxRaw);
        if ($taxRaw === '') {
            $taxRate = '7.00';
        } elseif (is_numeric($taxRaw)) {
            $taxRate = number_format((float) $taxRaw, 2, '.', '');
            if (!in_array((float) $taxRate, MenuItem::TAX_RATES, true)) {
                $warn("MwSt-Satz {$taxRate} % nicht zulässig (nur 7 % oder 19 %) – 7 % übernommen.");
                $taxRate = '7.00';
            }
        } else {
            $warn("MwSt „{$data['mwst']}“ nicht lesbar – 7 % übernommen.");
            $taxRate = '7.00';
        }

        // Allergene (Buchstaben) / Zusatzstoffe (Nummern)
        [$allergenIds, $unknownAllergens] = $this->mapCodes($data['allergene'] ?? '', $allergenCodes, true);
        if ($unknownAllergens) {
            $warn('Unbekannte Allergen-Codes übersprungen: ' . implode(', ', $unknownAllergens));
        }

        [$additiveIds, $unknownAdditives] = $this->mapCodes($data['zusatzstoffe'] ?? '', $additiveCodes, false);
        if ($unknownAdditives) {
            $warn('Unbekannte Zusatzstoff-Codes übersprungen: ' . implode(', ', $unknownAdditives));
        }

        return [
            'line'          => $lineNumber,
            'status'        => $status,
            'messages'      => $messages,
            'duplicate'     => $duplicate,
            'name'          => $name,
            'description'   => $data['beschreibung'] ?? '',
            'portion_size'  => $data['portionsgroesse'] ?? '',
            'category'      => ($data['kategorie'] ?? '') !== '' ? $data['kategorie'] : 'Sonstiges',
            'price'         => $price,
            'tax_rate'      => $taxRate,
            'available'     => $this->parseBool($data['verfuegbar'] ?? '', true),
            'is_vegetarian' => $this->parseBool($data['vegetarisch'] ?? '', false),
            'is_vegan'      => $this->parseBool($data['vegan'] ?? '', false),
            'is_alcoholic'  => $this->parseBool($data['alkohol'] ?? '', false),
            'allergen_ids'  => $allergenIds,
            'additive_ids'  => $additiveIds,
        ];
    }

    /** @return array{0: array, 1: array} [ids, unbekannte Codes] */
    protected function mapCodes(string $raw, array $codeMap, bool $uppercase): array
    {
        if (trim($raw) === '') {
            return [[], []];
        }

        $ids = [];
        $unknown = [];

        foreach (preg_split('/[,;\s\/]+/', trim($raw)) as $code) {
            if ($code === '') {
                continue;
            }
            $code = $uppercase ? mb_strtoupper($code) : (ltrim($code, '0') ?: '0');
            if (isset($codeMap[$code])) {
                $ids[] = $codeMap[$code];
            } else {
                $unknown[] = $code;
            }
        }

        return [array_values(array_unique($ids)), $unknown];
    }

    protected function parseBool(string $value, bool $default): bool
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return $default;
        }

        return in_array($value, ['ja', 'j', 'yes', 'true', '1', 'x', 'wahr'], true);
    }

    protected function normalizeEncoding(string $content): string
    {
        // UTF-8 BOM entfernen
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    protected function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') >= substr_count($headerLine, ',') ? ';' : ',';
    }
}
