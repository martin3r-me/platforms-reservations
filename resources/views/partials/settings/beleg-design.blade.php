{{-- Beleg-Design --}}
<x-nx-card flush>
    <div class="flex items-start gap-2.5 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-document-text', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
        <div class="min-w-0">
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Beleg-Design</h2>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-[color:var(--nx-muted)]">
                Gilt für Bestellbestätigung und Bewirtungsbeleg. Der Absenderblock kommt aus den
                Rechnungsangaben darüber.
            </p>
        </div>
        <div class="ml-auto shrink-0">
            <x-nx-button :href="route('reservation.settings.receipt-preview')" target="_blank">
                @svg('heroicon-o-eye', 'w-4 h-4')
                <span>Testbeleg ansehen</span>
            </x-nx-button>
        </div>
    </div>

    <div class="space-y-4 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- Logo --}}
            <div>
                <label class="block text-xs font-medium text-[color:var(--nx-text)]">Logo</label>
                @if ($this->setting->imageFile)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ $this->setting->imageUrl('medium_1_1') }}" alt="Logo"
                            class="h-12 w-auto rounded border border-[color:var(--nx-line)] bg-white p-1" />
                        <x-nx-button wire:click="removeReceiptLogo" wire:confirm="Logo entfernen?">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            <span>Entfernen</span>
                        </x-nx-button>
                    </div>
                @else
                    <div class="mt-2">
                        @include('reservation::partials.datei-upload', [
                            'model' => 'receiptLogo',
                            'hint'  => 'PNG mit transparentem Hintergrund empfohlen · max. 4 MB.',
                        ])
                    </div>
                @endif
            </div>

            {{-- Akzentfarbe --}}
            <div>
                <label class="block text-xs font-medium text-[color:var(--nx-text)]">Akzentfarbe</label>
                <p class="mt-1 text-xs text-[color:var(--nx-muted)]">Linien und Hervorhebungen im Beleg.</p>
                <div class="mt-2 flex items-center gap-2">
                    <input type="color" wire:model.live="receiptAccentColor"
                        value="{{ $receiptAccentColor ?: '#285567' }}"
                        class="h-9 w-14 cursor-pointer rounded border border-[color:var(--nx-line)] bg-transparent p-0.5" />
                    <div class="w-28">
                        <x-nx-input-text name="receiptAccentColor" label="" size="sm"
                            :value="$receiptAccentColor"
                            wire:model.live="receiptAccentColor" placeholder="#285567" errorKey="receiptAccentColor" />
                    </div>
                </div>
            </div>
        </div>

        <x-nx-input-textarea name="receiptFooterText" label="Fußzeile (optional)" rows="3"
            :value="$receiptFooterText"
            wire:model="receiptFooterText" errorKey="receiptFooterText"
            placeholder="z. B. Bankverbindung, Hinweise – erscheint unten auf dem Beleg." />
    </div>
</x-nx-card>
