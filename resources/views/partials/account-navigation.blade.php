{{-- Account links (spec 003, RF-04). $variant: "menu" (desktop dropdown) or "navlist" (mobile menu). --}}
@php
    $links = [
        ['label' => __('Perfil'), 'icon' => 'user', 'route' => 'settings.profile'],
        ['label' => __('Contraseña'), 'icon' => 'key', 'route' => 'settings.password'],
        ['label' => __('Apariencia'), 'icon' => 'swatch', 'route' => 'settings.appearance'],
    ];
@endphp

@foreach ($links as $link)
    @if ($variant === 'menu')
        <flux:menu.item :icon="$link['icon']" :href="route($link['route'])" wire:navigate>{{ $link['label'] }}</flux:menu.item>
    @else
        <flux:navlist.item :icon="$link['icon']" :href="route($link['route'])" :current="request()->routeIs($link['route'])" wire:navigate>{{ $link['label'] }}</flux:navlist.item>
    @endif
@endforeach

<form method="POST" action="{{ route('logout') }}" class="w-full">
    @csrf
    @if ($variant === 'menu')
        <flux:menu.separator />
        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
            {{ __('Cerrar sesión') }}
        </flux:menu.item>
    @else
        <flux:navlist.item as="button" type="submit" icon="arrow-right-start-on-rectangle">
            {{ __('Cerrar sesión') }}
        </flux:navlist.item>
    @endif
</form>
