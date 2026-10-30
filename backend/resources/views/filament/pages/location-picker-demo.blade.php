<x-filament-panels::page>
    <form wire:submit="check">
        {{ $this->form }}

        <div style="margin-top: 1.5rem">
            <x-filament::button type="submit">Перевірити форму</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
