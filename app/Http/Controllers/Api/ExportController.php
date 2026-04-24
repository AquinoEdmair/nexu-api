<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Models\YieldLogUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ExportController extends Controller
{
    private const MAX_RECORDS = 5000;

    /**
     * Return all operation records for the authenticated user within the given date range.
     *
     * Query params:
     *   sections    string  Comma-separated: transactions,withdrawals,yields (default: all)
     *   date_from   string  ISO date (Y-m-d), optional
     *   date_to     string  ISO date (Y-m-d), optional
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'sections'  => ['nullable', 'string'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $sections = $this->parseSections($request->input('sections', 'transactions,withdrawals,yields'));
        $from     = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : null;
        $to       = $request->filled('date_to')   ? Carbon::parse($request->input('date_to'))->endOfDay()   : null;

        $result = [];

        if (in_array('transactions', $sections, true)) {
            $result['transactions'] = $this->fetchTransactions($user->id, $from, $to);
        }

        if (in_array('withdrawals', $sections, true)) {
            $result['withdrawals'] = $this->fetchWithdrawals($user->id, $from, $to);
        }

        if (in_array('yields', $sections, true)) {
            $result['yields'] = $this->fetchYields($user->id, $from, $to);
        }

        return response()->json(['data' => $result]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /** @return array<string> */
    private function parseSections(string $raw): array
    {
        $allowed = ['transactions', 'withdrawals', 'yields'];
        return array_values(array_intersect(
            array_map('trim', explode(',', $raw)),
            $allowed
        ));
    }

    /** @return array<array<string, mixed>> */
    private function fetchTransactions(string $userId, ?Carbon $from, ?Carbon $to): array
    {
        return Transaction::where('user_id', $userId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->limit(self::MAX_RECORDS)
            ->get()
            ->map(fn (Transaction $tx) => [
                'Fecha'       => $tx->created_at->format('d/m/Y H:i:s'),
                'Tipo'        => $this->translateType($tx->type),
                'Moneda'      => $tx->currency,
                'Monto bruto' => (float) $tx->amount,
                'Comisión'    => (float) ($tx->fee_amount ?? 0),
                'Monto neto'  => (float) ($tx->net_amount ?? $tx->amount),
                'Estado'      => $this->translateStatus($tx->status),
                'Referencia'  => $tx->external_tx_id ?? "REF-{$tx->id}",
            ])
            ->toArray();
    }

    /** @return array<array<string, mixed>> */
    private function fetchWithdrawals(string $userId, ?Carbon $from, ?Carbon $to): array
    {
        return WithdrawalRequest::where('user_id', $userId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->limit(self::MAX_RECORDS)
            ->get()
            ->map(fn (WithdrawalRequest $w) => [
                'Fecha'             => $w->created_at->format('d/m/Y H:i:s'),
                'Moneda'            => $w->currency,
                'Monto solicitado'  => (float) $w->amount,
                'Comisión'          => (float) ($w->fee_amount ?? 0),
                'Monto neto'        => (float) ($w->net_amount ?? $w->amount),
                'Estado'            => $this->translateWithdrawalStatus($w->status),
                'Dirección destino' => $w->destination_address,
                'TX Hash'           => $w->tx_hash ?? '—',
                'Motivo rechazo'    => $w->rejection_reason ?? '—',
                'Revisado el'       => $w->reviewed_at?->format('d/m/Y H:i:s') ?? '—',
            ])
            ->toArray();
    }

    /** @return array<array<string, mixed>> */
    private function fetchYields(string $userId, ?Carbon $from, ?Carbon $to): array
    {
        return YieldLogUser::where('user_id', $userId)
            ->with('yieldLog:id,type,value,description,applied_at')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->limit(self::MAX_RECORDS)
            ->get()
            ->map(fn (YieldLogUser $y) => [
                'Fecha'            => $y->created_at->format('d/m/Y H:i:s'),
                'Tipo rendimiento' => $y->yieldLog?->type === 'percentage' ? 'Porcentaje' : 'Monto fijo',
                'Valor aplicado'   => (float) ($y->yieldLog?->value ?? 0),
                'Balance antes'    => (float) $y->balance_before,
                'Balance después'  => (float) $y->balance_after,
                'Rendimiento'      => (float) $y->amount_applied,
                'Estado'           => $y->status === 'applied' ? 'Aplicado' : ucfirst($y->status),
                'Descripción'      => $y->yieldLog?->description ?? '—',
            ])
            ->toArray();
    }

    private function translateType(string $type): string
    {
        return match ($type) {
            'deposit'             => 'Depósito',
            'withdrawal'          => 'Retiro',
            'yield'               => 'Rendimiento',
            'commission'          => 'Comisión',
            'investment'          => 'Inversión',
            'referral_commission' => 'Comisión referido',
            'admin_adjustment'    => 'Ajuste admin',
            default               => $type,
        };
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'confirmed'  => 'Confirmado',
            'completed'  => 'Completado',
            'pending'    => 'Pendiente',
            'failed'     => 'Fallido',
            'rejected'   => 'Rechazado',
            'processing' => 'En proceso',
            default      => $status,
        };
    }

    private function translateWithdrawalStatus(string $status): string
    {
        return match ($status) {
            'pending'    => 'Pendiente',
            'approved'   => 'Aprobado',
            'rejected'   => 'Rechazado',
            'processing' => 'En proceso',
            'completed'  => 'Completado',
            'cancelled'  => 'Cancelado',
            default      => $status,
        };
    }
}
