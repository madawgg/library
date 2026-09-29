<?php

use App\Enums\Theme;
use App\Services\UserPreferenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Apariencia')] class extends Component {
    public string $theme = '';

    public function mount(): void
    {
        $this->theme = Auth::user()->theme->value;
    }

    public function updatedTheme(UserPreferenceService $preferences): void
    {
        $validated = $this->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
        ]);

        $preferences->updateTheme(Auth::user(), Theme::from($validated['theme']));

        // Full reload so the <html> element gets the new theme class.
        $this->redirectRoute('settings.appearance');
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Apariencia')" :subheading="__('Elige el tema de la aplicación. Se guarda en tu cuenta.')">
        <flux:radio.group wire:model.live="theme" variant="segmented" :label="__('Tema')">
            @foreach (App\Enums\Theme::cases() as $option)
                <flux:radio value="{{ $option->value }}" :icon="$option === App\Enums\Theme::Dark ? 'moon' : 'sun'">{{ $option->label() }}</flux:radio>
            @endforeach
        </flux:radio.group>
    </x-settings.layout>
</div>
