{{-- resources/views/filament/yield-preview.blade.php --}}
<div class="space-y-4">
    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            'Usuarios afectados'     => (string) $preview->totalUsers,
            'Total a distribuir'     => '$' . number_format((float) $preview->totalAmountToApply, 2),
            'Balance sistema antes'  => '$' . number_format((float) $preview->systemBalanceBefore, 2),
            'Balance sistema después'=> '$' . number_format((float) $preview->systemBalanceAfter, 2),
        ] as $label => $value)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Warning: users going negative --}}
    @if ($preview->hasUsersGoingNegative)
        <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
            <p class="font-semibold mb-1">⚠ Usuarios con saldo insuficiente</p>
            @if ($policy === 'skip')
                <p class="text-sm">
                    {{ $preview->usersSkippedByPolicy }} usuario(s) serán
                    <strong>omitidos</strong> porque su saldo no cubre la deducción.
                </p>
            @else
                <p class="text-sm">
                    Algunos usuarios serán llevados a <strong>$0</strong>
                    (política: aplicar hasta el límite disponible).
                </p>
            @endif
        </div>
    @endif

    {{-- Warning: zero distribution --}}
    @if ((float) $preview->totalAmountToApply === 0.0 && $preview->totalUsers > 0)
        <div class="rounded-md border border-gray-300 bg-gray-50 p-4 text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <p class="text-sm">⚠ El monto total a distribuir es <strong>$0</strong>. Verifica el valor configurado.</p>
        </div>
    @endif

    {{-- Warning: no users --}}
    @if ($preview->totalUsers === 0)
        <div class="rounded-md border border-gray-300 bg-gray-50 p-4 text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <p class="text-sm">⚠ No hay usuarios activos con wallet para aplicar este rendimiento.</p>
        </div>
    @endif

    {{-- Preview table --}}
    @if ($preview->userRows->isNotEmpty())
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        @foreach (['left' => 'Usuario', 'right' => 'Balance antes', 'right2' => 'Monto', 'right3' => 'Balance después'] as $align => $th)
                            <th class="px-4 py-3 text-{{ str_contains($align, 'right') ? 'right' : 'left' }} font-medium text-gray-600 dark:text-gray-300">
                                {{ $th }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($preview->userRows as $row)
                        @php
                            $rowClass = $row->wouldBeSkipped
                                ? 'opacity-40 line-through'
                                : ($row->wouldGoNegative ? 'text-red-600 dark:text-red-400' : '');
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="px-4 py-2">{{ $row->userEmail }}</td>
                            <td class="px-4 py-2 text-right font-mono">${{ number_format((float) $row->balanceBefore, 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono">${{ number_format((float) $row->amountToApply, 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono">${{ number_format((float) $row->balanceAfter, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php $shown = $preview->userRows->count(); $total = $preview->totalUsers; @endphp
        @if ($total > $shown)
            <p class="text-xs text-gray-500 mt-2">
                Mostrando {{ $shown }} de {{ $total }} usuarios. Se procesarán todos al confirmar.
            </p>
        @endif
    @endif
</div>
