<?php

use App\Services\UserAccountService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Contraseña')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(UserAccountService $accounts): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $accounts->updatePassword(Auth::user(), $validated['password']);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Contraseña')" :subheading="__('Usa una contraseña larga y difícil de adivinar.')">
        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                id="update_password_current_passwordpassword"
                label="{{ __('Contraseña actual') }}"
                type="password"
                name="current_password"
                required
                autocomplete="current-password"
            />
            <flux:input
                wire:model="password"
                id="update_password_password"
                label="{{ __('Nueva contraseña') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                id="update_password_password_confirmation"
                label="{{ __('Confirmar contraseña') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Guardar') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Guardado.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
