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
    :class="over ? 'border-[var(--ui-primary)] bg-[var(--ui-primary-10)]' : 'border-[var(--ui-border)]'"
    class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors hover:border-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]"
>
    @svg('heroicon-o-arrow-up-tray', 'w-6 h-6 text-[var(--ui-muted)]')
    <span class="text-sm font-medium text-[var(--ui-secondary)]">
        Bild hierher ziehen oder <span class="text-[var(--ui-primary)] underline">auswählen</span>
    </span>
    <span class="text-[11px] text-[var(--ui-muted)]">{{ $hint }}</span>

    <input x-ref="file" type="file" wire:model="{{ $model }}" accept="{{ $accept }}" class="hidden" />

    <span wire:loading wire:target="{{ $model }}" class="mt-1 inline-flex items-center gap-1 text-[11px] text-[var(--ui-primary)]">
        @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5 animate-spin')
        Wird hochgeladen…
    </span>
</div>
@error($model) <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p> @enderror
