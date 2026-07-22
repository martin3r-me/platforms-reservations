{{--
    Wiederverwendbare Bild-Upload-Dropzone (Klick + Drag & Drop) für Livewire.

    Erwartet:
    - $model   (string)  Name der wire:model-Property (z. B. 'itemImage')
    - $hint    (string)  Formathinweis unter der Zone
    - $accept  (string)  optional, Standard: JPG/PNG/WebP
--}}
@php $accept = $accept ?? 'image/jpeg,image/png,image/webp'; @endphp
<div
    x-data="{ over: false }"
    x-on:dragover.prevent="over = true"
    x-on:dragleave.prevent="over = false"
    x-on:drop.prevent="over = false; if ($event.dataTransfer.files.length) { $refs.file.files = $event.dataTransfer.files; $refs.file.dispatchEvent(new Event('change', { bubbles: true })); }"
    x-on:click="$refs.file.click()"
    role="button"
    tabindex="0"
    x-on:keydown.enter.prevent="$refs.file.click()"
    x-on:keydown.space.prevent="$refs.file.click()"
    :class="over ? 'border-[color:var(--nx-accent)] bg-[color:var(--nx-accent-soft)]' : 'border-[color:var(--nx-line-strong)]'"
    class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-[8px] border-2 border-dashed px-4 py-6 text-center transition-colors hover:border-[color:var(--nx-accent)] hover:bg-[color:var(--nx-hover)]"
>
    @svg('heroicon-o-arrow-up-tray', 'w-6 h-6 text-[color:var(--nx-muted)]')
    <span class="text-sm font-medium text-[color:var(--nx-text)]">
        Bild hierher ziehen oder <span class="underline">auswählen</span>
    </span>
    <span class="text-[11px] text-[color:var(--nx-muted)]">{{ $hint }}</span>

    <input x-ref="file" type="file" wire:model="{{ $model }}" accept="{{ $accept }}" class="hidden" />

    <span wire:loading wire:target="{{ $model }}" class="mt-1 inline-flex items-center gap-1 text-[11px] text-[color:var(--nx-muted)]">
        @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5 animate-spin')
        Wird hochgeladen…
    </span>
</div>
@error($model) <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p> @enderror
