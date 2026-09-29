<x-layouts.app.header :title="$title ?? null">
    <flux:main container id="contenido-principal" tabindex="-1">
        {{ $slot }}
    </flux:main>
</x-layouts.app.header>
