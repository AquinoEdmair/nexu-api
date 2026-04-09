<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Métricas financieras globales</x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total depositado</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary->totalDeposited, 2) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total retirado</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary->totalWithdrawn, 2) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total rendimientos</p>
                <p class="mt-1 text-xl font-bold {{ $summary->totalYieldApplied >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    ${{ number_format($summary->totalYieldApplied, 2) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Comisiones cobradas</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary->totalCommissions, 2) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Comisiones de referidos</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary->totalReferralCommissions, 2) }}
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Usuarios con balance</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">
                    {{ number_format($summary->usersWithBalance) }}
                </p>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>
