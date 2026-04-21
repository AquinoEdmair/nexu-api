<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Profile Section --}}
        <x-filament::section>
            <x-slot name="heading">Información personal</x-slot>

            <form wire:submit.prevent="saveProfile" class="space-y-4">
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="name"
                            placeholder="Nombre completo"
                        />
                    </x-filament::input.wrapper>
                    @error('name') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-filament::button type="submit">
                        Guardar perfil
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Change Password Section --}}
        <x-filament::section>
            <x-slot name="heading">Cambiar contraseña</x-slot>

            <form wire:submit.prevent="changePassword" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña actual</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="password"
                            wire:model="currentPassword"
                        />
                    </x-filament::input.wrapper>
                    @error('currentPassword') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nueva contraseña (mín. 12 caracteres)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="password"
                            wire:model="newPassword"
                        />
                    </x-filament::input.wrapper>
                    @error('newPassword') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmar nueva contraseña</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="password"
                            wire:model="newPasswordConfirmation"
                        />
                    </x-filament::input.wrapper>
                    @error('newPasswordConfirmation') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-filament::button type="submit" color="warning">
                        Cambiar contraseña
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

    </div>
</x-filament-panels::page>
