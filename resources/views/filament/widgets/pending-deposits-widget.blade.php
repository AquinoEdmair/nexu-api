<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Depósitos sin confirmar</x-slot>
        <x-slot name="description">Botones de pago creados aún en espera — últimos 10</x-slot>

        @if ($invoices->isEmpty())
            <div class="flex items-center gap-2 py-4 text-success-600">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                <span class="text-sm font-medium">No hay depósitos pendientes</span>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="pb-2 font-medium">Usuario</th>
                        <th class="pb-2 font-medium">Monto</th>
                        <th class="pb-2 font-medium">Red</th>
                        <th class="pb-2 font-medium">Estado</th>
                        <th class="pb-2 font-medium">Hace</th>
                        <th class="pb-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($invoices as $invoice)
                        @php
                            $isExpired = $invoice->expires_at && $invoice->expires_at->isPast();
                        @endphp
                        <tr class="py-1">
                            <td class="py-2 pr-3">
                                <div class="font-medium text-gray-900 dark:text-gray-100 leading-tight">
                                    {{ $invoice->user?->name ?? '—' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $invoice->user?->email ?? '' }}
                                </div>
                            </td>
                            <td class="py-2 pr-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                ${{ number_format((float) $invoice->amount_expected, 2) }}
                                <span class="text-xs text-gray-400">{{ $invoice->currency }}</span>
                            </td>
                            <td class="py-2 pr-3 text-gray-500 dark:text-gray-400 text-xs">
                                {{ $invoice->network ?? $invoice->currency }}
                            </td>
                            <td class="py-2 pr-3">
                                @if ($isExpired)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400">
                                        Expirado
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400">
                                        En espera
                                    </span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                {{ $invoice->created_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="py-2">
                                <button
                                    wire:click="sendFollowUp('{{ $invoice->id }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="sendFollowUp('{{ $invoice->id }}')"
                                    class="text-xs text-primary-600 hover:text-primary-500 font-medium disabled:opacity-50 whitespace-nowrap"
                                    title="Enviar email de seguimiento a {{ $invoice->user?->email }}"
                                >
                                    <span wire:loading.remove wire:target="sendFollowUp('{{ $invoice->id }}')">
                                        Seguimiento
                                    </span>
                                    <span wire:loading wire:target="sendFollowUp('{{ $invoice->id }}')">
                                        Enviando…
                                    </span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
