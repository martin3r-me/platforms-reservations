<?php

namespace Platform\Reservation\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\ContextFile;

/**
 * Bild über platform-core ContextFile (Spalte: image_context_file_id).
 * In Listen `->with('imageFile.variants')` eager-laden (kein N+1).
 */
trait HasContextImage
{
    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class, 'image_context_file_id');
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
}
