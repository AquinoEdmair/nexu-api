<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top referidores</x-slot>
        <x-slot name="description">Por ganancias totales</x-slot>

        @if ($referrers->isEmpty())
            <p class="py-4 text-sm text-gray-500 dark:text-gray-400">Sin datos de referidos aún.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="pb-2 font-medium">Referidor</th>
                        <th class="pb-2 font-medium">Referidos</th>
                        <th class="pb-2 font-medium">Ganancias</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($referrers as $row)
                        <tr>
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                {{ $row->referrer?->name ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                {{ number_format((int) $row->referral_count) }}
                            </td>
                            <td class="py-2 text-gray-700 dark:text-gray-300">
                                ${{ number_format((float) $row->total_earned, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
