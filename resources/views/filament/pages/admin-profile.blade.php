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

        {{-- Two-Factor Authentication Section --}}
        <x-filament::section>
            <x-slot name="heading">Autenticación de dos factores (2FA)</x-slot>

            @php
                /** @var \App\Models\Admin $admin */
                $admin = auth()->user();
            @endphp

            @if ($admin->hasTwoFactorEnabled())
                <p class="text-sm text-green-600 dark:text-green-400 mb-4">
                    2FA está <strong>activo</strong> desde {{ $admin->two_factor_confirmed_at?->format('d/m/Y H:i') }}.
                </p>

                <div class="space-y-4">
                    {{-- Disable 2FA --}}
                    <div class="border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-red-700 dark:text-red-400 mb-2">Desactivar 2FA</h3>
                        <form wire:submit.prevent="disableTwoFactor" class="space-y-3">
                            <div>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="password"
                                        wire:model="disableTwoFactorPassword"
                                        placeholder="Confirma tu contraseña"
                                    />
                                </x-filament::input.wrapper>
                                @error('disableTwoFactorPassword') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <x-filament::button type="submit" color="danger" size="sm">
                                Desactivar 2FA
                            </x-filament::button>
                        </form>
                    </div>

                    {{-- Regenerate Recovery Codes --}}
                    @if ($admin->hasRecoveryCodes())
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Regenerar recovery codes</h3>
                        <form wire:submit.prevent="regenerateRecoveryCodes" class="space-y-3">
                            <div>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="password"
                                        wire:model="recoveryCodesPassword"
                                        placeholder="Confirma tu contraseña"
                                    />
                                </x-filament::input.wrapper>
                                @error('recoveryCodesPassword') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <x-filament::button type="submit" color="gray" size="sm">
                                Regenerar códigos de recuperación
                            </x-filament::button>
                        </form>
                    </div>
                    @endif
                </div>

            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    2FA no está activado en tu cuenta. Te recomendamos activarlo para mayor seguridad.
                </p>
                <x-filament::button wire:click="enableTwoFactor">
                    Activar 2FA
                </x-filament::button>
            @endif
        </x-filament::section>

    </div>

    {{-- QR Code Modal --}}
    @if ($showQrModal)
    <x-filament::modal id="qr-modal" :open="true" width="lg">
        <x-slot name="heading">Configurar 2FA — escanea el código QR</x-slot>
        <x-slot name="description">
            Escanea este código QR con tu aplicación de autenticación (Google Authenticator, Authy, etc.).
        </x-slot>

        <div class="text-center space-y-4">
            <div class="flex justify-center">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($qrCodeUrl ?? '') !!}
            </div>
            <p class="text-xs text-gray-500">Clave manual: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">{{ $secretKey }}</code></p>
        </div>

        <x-slot name="footerActions">
            <x-filament::button wire:click="openConfirmModal">
                Ya lo escaneé — continuar
            </x-filament::button>
            <x-filament::button color="gray" wire:click="closeModals">
                Cancelar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
    @endif

    {{-- Confirm 2FA Modal --}}
    @if ($showConfirmModal)
    <x-filament::modal id="confirm-2fa-modal" :open="true" width="md">
        <x-slot name="heading">Confirmar configuración 2FA</x-slot>
        <x-slot name="description">
            Ingresa el código de 6 dígitos de tu aplicación de autenticación para confirmar.
        </x-slot>

        <form wire:submit.prevent="confirmTwoFactor" class="space-y-4">
            <div>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="twoFactorCode"
                        placeholder="000000"
                        maxlength="6"
                    />
                </x-filament::input.wrapper>
                @error('twoFactorCode') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot name="footerActions">
            <x-filament::button wire:click="confirmTwoFactor">
                Confirmar
            </x-filament::button>
            <x-filament::button color="gray" wire:click="closeModals">
                Cancelar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
    @endif

    {{-- Recovery Codes Modal --}}
    @if ($showCodesModal && $shownRecoveryCodes)
    <x-filament::modal id="codes-modal" :open="true" width="md">
        <x-slot name="heading">Códigos de recuperación</x-slot>
        <x-slot name="description">
            Guarda estos códigos en un lugar seguro. No se volverán a mostrar.
        </x-slot>

        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 font-mono text-sm space-y-1">
            @foreach ($shownRecoveryCodes as $code)
                <div class="select-all">{{ $code }}</div>
            @endforeach
        </div>

        <x-slot name="footerActions">
            <x-filament::button wire:click="closeModals">
                Los guardé — cerrar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
    @endif
</x-filament-panels::page>
