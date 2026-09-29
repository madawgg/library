<?php

use App\Services\PasswordResetService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Recuperar contraseña')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(PasswordResetService $passwordReset): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $passwordReset->sendResetLink($this->email);

        session()->flash('status', __('Si la cuenta existe, te enviaremos un enlace de recuperación.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Recuperar contraseña')" :description="__('Introduce tu correo electrónico y te enviaremos un enlace para restablecer la contraseña.')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" label="{{ __('Correo electrónico') }}" type="email" name="email" required autofocus placeholder="email@example.com" />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Enviar enlace de recuperación') }}</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        {{ __('O vuelve a') }}
        <x-text-link href="{{ route('login') }}">{{ __('iniciar sesión') }}</x-text-link>
    </div>
</div>
