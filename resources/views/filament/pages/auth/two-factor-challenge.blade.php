<x-filament-panels::page.simple>
    <x-filament::section>
        <x-slot name="heading">Verificación en dos pasos</x-slot>
        <x-slot name="description">
            Ingresa el código de tu aplicación de autenticación para continuar.
        </x-slot>

        {{-- Tab switcher --}}
        <div class="flex space-x-2 mb-6">
            <button
                wire:click="switchTab('totp')"
                class="px-3 py-1.5 text-sm rounded-lg {{ $activeTab === 'totp' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}"
            >
                Código TOTP
            </button>
            <button
                wire:click="switchTab('recovery')"
                class="px-3 py-1.5 text-sm rounded-lg {{ $activeTab === 'recovery' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}"
            >
                Código de recuperación
            </button>
        </div>

        {{-- TOTP form --}}
        @if ($activeTab === 'totp')
        <form wire:submit.prevent="verifyTotp" class="space-y-4">
            @if ($error)
                <div class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-lg px-3 py-2">
                    {{ $error }}
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Código de 6 dígitos
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="code"
                        placeholder="000000"
                        maxlength="6"
                        autofocus
                    />
                </x-filament::input.wrapper>
            </div>

            <x-filament::button type="submit" class="w-full">
                Verificar
            </x-filament::button>
        </form>
        @endif

        {{-- Recovery code form --}}
        @if ($activeTab === 'recovery')
        <form wire:submit.prevent="verifyRecovery" class="space-y-4">
            @if ($recoveryError)
                <div class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-lg px-3 py-2">
                    {{ $recoveryError }}
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Código de recuperación
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="recoveryCode"
                        placeholder="xxxxx-xxxxx"
                        autofocus
                    />
                </x-filament::input.wrapper>
            </div>

            <x-filament::button type="submit" class="w-full">
                Usar código de recuperación
            </x-filament::button>
        </form>
        @endif

        <div class="mt-4 text-center">
            <a href="/admin/login" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                Volver al inicio de sesión
            </a>
        </div>
    </x-filament::section>
</x-filament-panels::page.simple>
