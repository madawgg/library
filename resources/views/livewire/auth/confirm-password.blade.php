<?php

use App\Services\AuthenticationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Confirmar contraseña')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(AuthenticationService $authentication): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $authentication->confirmPassword(Auth::user(), $this->password);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Confirmar contraseña')"
        :description="__('Es una zona protegida. Confirma tu contraseña para continuar.')"
    />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="confirmPassword" class="flex flex-col gap-6">
        <!-- Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Contraseña') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                :placeholder="__('Contraseña')"
            />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Confirmar') }}</flux:button>
    </form>
</div>
