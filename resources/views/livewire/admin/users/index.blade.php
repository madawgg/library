<?php

use App\Models\User;
use App\Services\UserAccountService;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Usuarios')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function delete(int $userId, UserAccountService $accounts): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('delete', $user);

        $accounts->deleteAccount($user);
    }

    public function with(UserAccountService $accounts): array
    {
        return ['users' => $accounts->listAccounts()];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Usuarios') }}</flux:heading>

        @can('create', App\Models\User::class)
            <flux:button variant="primary" :href="route('admin.users.create')" wire:navigate>
                {{ __('Nuevo usuario') }}
            </flux:button>
        @endcan
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ __('Listado de usuarios') }}</caption>
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th scope="col" class="py-3 pe-4 font-medium">{{ __('Nombre') }}</th>
                    <th scope="col" class="py-3 pe-4 font-medium">{{ __('Correo electrónico') }}</th>
                    <th scope="col" class="py-3 pe-4 font-medium">{{ __('Rol') }}</th>
                    <th scope="col" class="py-3 font-medium"><span class="sr-only">{{ __('Acciones') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="py-3 pe-4">{{ $user->name }}</td>
                        <td class="py-3 pe-4">{{ $user->email }}</td>
                        <td class="py-3 pe-4">{{ $user->role->label() }}</td>
                        <td class="py-3">
                            <div class="flex justify-end gap-2">
                                @can('manageLibrary', $user)
                                    <flux:button size="sm" variant="ghost" :href="route('admin.users.rooms', $user)" wire:navigate>
                                        {{ __('Salas y estanterías') }}<span class="sr-only"> {{ __('de :name', ['name' => $user->name]) }}</span>
                                    </flux:button>
                                @endcan

                                @can('update', $user)
                                    <flux:button size="sm" :href="route('admin.users.edit', $user)" wire:navigate>
                                        {{ __('Editar') }}<span class="sr-only"> {{ $user->name }}</span>
                                    </flux:button>
                                @endcan

                                @can('delete', $user)
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="delete({{ $user->id }})"
                                        wire:confirm="{{ __('¿Seguro que quieres eliminar la cuenta de :name? Esta acción no se puede deshacer.', ['name' => $user->name]) }}"
                                    >
                                        {{ __('Eliminar') }}<span class="sr-only"> {{ $user->name }}</span>
                                    </flux:button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
