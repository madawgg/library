<?php

use App\Services\AuthenticationService;
use App\Services\UserAccountService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(UserAccountService $accounts, AuthenticationService $authentication): void
    {
        $user = Auth::user();

        $this->authorize('deleteOwnAccount', $user);

        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $authentication->logout();
        $accounts->deleteAccount($user);

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Eliminar cuenta') }}</flux:heading>
        <flux:subheading>{{ __('Elimina tu cuenta y todos sus datos') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Eliminar cuenta') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('¿Seguro que quieres eliminar tu cuenta?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Al eliminar tu cuenta se borrarán para siempre todos sus datos. Escribe tu contraseña para confirmar.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" id="password" label="{{ __('Contraseña') }}" type="password" name="password" />

            <div class="flex justify-end space-x-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Eliminar cuenta') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
