<x-filament-panels::page>
    <div class="space-y-6">

        <x-filament::section>
            <x-slot name="heading">Notificaciones al administrador</x-slot>
            <x-slot name="description">
                Email adicional que recibe copia (BCC) de todas las alertas del panel: depósitos, retiros, tickets y nuevos usuarios.
                Útil para un buzón compartido del equipo (ej. ops@nexu.com). Dejar vacío para desactivar.
            </x-slot>

            <form wire:submit.prevent="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email de notificaciones
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="email"
                            wire:model="adminNotificationEmail"
                            placeholder="ops@nexu.com"
                        />
                    </x-filament::input.wrapper>
                    @error('adminNotificationEmail')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-filament::button type="submit">
                        Guardar
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

    </div>
</x-filament-panels::page>
