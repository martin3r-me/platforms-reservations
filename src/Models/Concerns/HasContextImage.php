<?php

namespace Platform\Reservation\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;

/**
 * Bild über platform-core ContextFile. Standard-Spalte: image_context_file_id;
 * per {@see contextImageColumn()} überschreibbar (z.B. FloorPlan → background_context_file_id).
 *
 * Kapselt die komplette Anbindung an den platform-core ContextFileService
 * (Upload/Ersetzen/Löschen), damit die Livewire-Komponenten das nicht mehrfach
 * ausprogrammieren. platform-core selbst wird NICHT verändert.
 *
 * In Listen `->with('imageFile.variants')` eager-laden (kein N+1).
 */
trait HasContextImage
{
    /** Spalte, in der die ContextFile-ID abgelegt ist. */
    protected function contextImageColumn(): string
    {
        return 'image_context_file_id';
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class, $this->contextImageColumn());
    }

    /**
     * Signierte URL der gewünschten Variante (z.B. 'medium_1_1', 'medium_16_9').
     * Fallback: gleiche Ratio in anderer Größe, sonst Original (Varianten
     * entstehen asynchron bzw. entfallen bei kleinen Originalen).
     */
    public function imageUrl(string $variant = 'medium_1_1'): ?string
    {
        $file = $this->imageFile;

        if (!$file) {
            return null;
        }

        $match = $file->variants->firstWhere('variant_type', $variant);

        if (!$match && str_contains($variant, '_')) {
            $ratio = substr($variant, strpos($variant, '_') + 1);
            $match = $file->variants->first(
                fn ($v) => str_ends_with($v->variant_type, '_' . $ratio)
            );
        }

        return $match?->url ?? $file->url;
    }

    /**
     * Bild hochladen (via platform-core), am Model verknüpfen und ein zuvor
     * verknüpftes Bild löschen. Gibt das Upload-Ergebnis des Services zurück.
     */
    public function setContextImage(UploadedFile $file, string $contextType, ?int $teamId, ?int $userId = null): array
    {
        $service = app(ContextFileService::class);

        $uploaded = $service->uploadForContext($file, $contextType, $this->getKey(), [
            'team_id' => $teamId,
            'user_id' => $userId,
        ]);

        $column = $this->contextImageColumn();
        $previousId = $this->{$column};

        // Erst neue Verknüpfung setzen (neues File existiert bereits) …
        $this->update([$column => $uploaded['id']]);

        // … dann altes File entfernen (Fehlen ist unkritisch).
        if ($previousId) {
            try {
                $service->delete($previousId, $teamId);
            } catch (\Throwable $e) {
                // Altes File bereits weg – Verknüpfung ist trotzdem ersetzt.
            }
        }

        $this->unsetRelation('imageFile');

        return $uploaded;
    }

    /**
     * Verknüpftes Bild löschen und Verknüpfung aufheben.
     */
    public function clearContextImage(?int $teamId): void
    {
        $column = $this->contextImageColumn();
        $previousId = $this->{$column};

        if (!$previousId) {
            return;
        }

        try {
            app(ContextFileService::class)->delete($previousId, $teamId);
        } catch (\Throwable $e) {
            // File bereits weg – Verknüpfung trotzdem lösen.
        }

        $this->update([$column => null]);
        $this->unsetRelation('imageFile');
    }
}
