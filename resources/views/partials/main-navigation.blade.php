{{-- Main navigation links (spec 003, RF-04). $variant: "navbar" (desktop header) or "navlist" (mobile menu). --}}
@php
    $links = [
        ['label' => __('Inicio'), 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard', 'visible' => true],
        ['label' => __('Usuarios'), 'icon' => 'users', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'visible' => auth()->user()->can('viewAny', App\Models\User::class)],
    ];
@endphp

@foreach ($links as $link)
    @continue(! $link['visible'])

    @if ($variant === 'navbar')
        <flux:navbar.item :icon="$link['icon']" :href="route($link['route'])" :current="request()->routeIs($link['active'])" wire:navigate>
            {{ $link['label'] }}
        </flux:navbar.item>
    @else
        <flux:navlist.item :icon="$link['icon']" :href="route($link['route'])" :current="request()->routeIs($link['active'])" wire:navigate>
            {{ $link['label'] }}
        </flux:navlist.item>
    @endif
@endforeach
