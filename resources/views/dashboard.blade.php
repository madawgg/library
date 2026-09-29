<x-layouts.app :title="__('Inicio')">
    <flux:heading size="xl" level="1">{{ __('Inicio') }}</flux:heading>
    <flux:subheading>{{ __('Hola, :name.', ['name' => auth()->user()->name]) }}</flux:subheading>
</x-layouts.app>
