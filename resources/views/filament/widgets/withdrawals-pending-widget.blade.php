<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Retiros pendientes</x-slot>
        <x-slot name="description">5 más antiguos — atender primero</x-slot>

        @if ($pending->isEmpty())
            <div class="flex items-center gap-2 py-4 text-success-600">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                <span class="text-sm font-medium">No hay retiros pendientes</span>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="pb-2 font-medium">Usuario</th>
                        <th class="pb-2 font-medium">Monto</th>
                        <th class="pb-2 font-medium">Moneda</th>
                        <th class="pb-2 font-medium">Hace</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($pending as $request)
                        <tr class="py-1">
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                {{ $request->user?->name ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                ${{ number_format((float) $request->amount, 2) }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                {{ $request->currency }}
                            </td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">
                                {{ $request->created_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                <a
                    href="{{ route('filament.admin.resources.withdrawal-requests.index', ['tableFilters[status][value]' => 'pending']) }}"
                    class="text-sm text-primary-600 hover:text-primary-500 font-medium"
                >
                    Ver todas las solicitudes →
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
