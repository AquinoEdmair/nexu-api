<div class="space-y-4">
    @if($transactions->isEmpty())
        <div class="text-center py-8 text-gray-400">
            <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-3 opacity-50" />
            <p class="text-sm font-medium">Sin transacciones registradas</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-800/50 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                        <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                        <th class="px-4 py-3 text-right font-semibold">Bruto</th>
                        <th class="px-4 py-3 text-right font-semibold">Comisión</th>
                        <th class="px-4 py-3 text-right font-semibold">Neto</th>
                        <th class="px-4 py-3 text-left font-semibold">Moneda</th>
                        <th class="px-4 py-3 text-left font-semibold">Estado</th>
                        <th class="px-4 py-3 text-left font-semibold">TX</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    @foreach($transactions as $tx)
                        @php
                            $typeColors = [
                                'deposit' => 'bg-blue-500/10 text-blue-400 ring-blue-500/20',
                                'withdrawal' => 'bg-amber-500/10 text-amber-400 ring-amber-500/20',
                                'yield' => 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20',
                                'commission' => 'bg-gray-500/10 text-gray-400 ring-gray-500/20',
                                'referral_commission' => 'bg-purple-500/10 text-purple-400 ring-purple-500/20',
                                'admin_adjustment' => 'bg-red-500/10 text-red-400 ring-red-500/20',
                                'investment' => 'bg-indigo-500/10 text-indigo-400 ring-indigo-500/20',
                            ];
                            $typeLabels = [
                                'deposit' => 'Depósito',
                                'withdrawal' => 'Retiro',
                                'yield' => 'Rendimiento',
                                'commission' => 'Comisión',
                                'referral_commission' => 'Com. Referido',
                                'admin_adjustment' => 'Ajuste admin',
                                'investment' => 'Inversión',
                            ];
                            $statusColors = [
                                'confirmed' => 'bg-emerald-500/10 text-emerald-400',
                                'pending' => 'bg-amber-500/10 text-amber-400',
                                'processing' => 'bg-blue-500/10 text-blue-400',
                                'rejected' => 'bg-red-500/10 text-red-400',
                            ];
                            $statusLabels = [
                                'confirmed' => 'Confirmado',
                                'pending' => 'Pendiente',
                                'processing' => 'Procesando',
                                'rejected' => 'Rechazado',
                            ];
                            $netPositive = (float) $tx->net_amount >= 0;
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-3 text-gray-300 text-xs whitespace-nowrap">
                                {{ $tx->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold ring-1 ring-inset {{ $typeColors[$tx->type] ?? 'bg-gray-500/10 text-gray-400 ring-gray-500/20' }}">
                                    {{ $typeLabels[$tx->type] ?? $tx->type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-300 text-xs font-mono">
                                ${{ number_format((float) $tx->amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-red-400/80 text-xs font-mono">
                                ${{ number_format((float) $tx->fee_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs font-bold {{ $netPositive ? 'text-emerald-400' : 'text-red-400' }}">
                                ${{ number_format((float) $tx->net_amount, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-500/10 text-gray-400">
                                    {{ $tx->currency ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold {{ $statusColors[$tx->status] ?? 'bg-gray-500/10 text-gray-400' }}">
                                    {{ $statusLabels[$tx->status] ?? $tx->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs font-mono max-w-[120px] truncate" title="{{ $tx->external_tx_id }}">
                                {{ $tx->external_tx_id ? Str::limit($tx->external_tx_id, 16) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 text-right">
            Mostrando las últimas {{ $transactions->count() }} transacciones
        </p>
    @endif
</div>
