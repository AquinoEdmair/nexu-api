<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CommissionConfigUpdated;
use App\Models\Admin;
use App\Models\CommissionConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CommissionService
{
    public const VALUE_MIN = 0.01;
    public const VALUE_MAX = 99.99;

    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Return the active commission rate for the given type.
     *
     * Uses a Redis cache (TTL 5 min). Falls back to a direct DB query if Redis
     * is unavailable. Returns 0.0 if no active config exists for the type.
     */
    public function getActiveRate(string $type): float
    {
        $fetch = fn (): float => (float) (
            CommissionConfig::active()->where('type', $type)->value('value') ?? 0.0
        );

        try {
            return (float) Cache::remember($this->cacheKey($type), self::CACHE_TTL, $fetch);
        } catch (\Throwable) {
            // Redis unavailable — fall back to DB directly
            return $fetch();
        }
    }

    /**
     * Create a new active config for the type, deactivating any previous one.
     *
     * @throws \InvalidArgumentException if value is outside [0.01, 99.99]
     * @throws \Throwable
     */
    public function updateConfig(
        string  $type,
        float   $value,
        ?string $description,
        Admin   $admin,
    ): CommissionConfig {
        $this->assertValidValue($value);

        $previous = null;

        $config = DB::transaction(function () use ($type, $value, $description, $admin, &$previous): CommissionConfig {
            $previous = CommissionConfig::active()
                ->byType($type)
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                $previous->update(['is_active' => false]);
            }

            return CommissionConfig::create([
                'type'        => $type,
                'value'       => $value,
                'is_active'   => true,
                'description' => $description,
                'created_by'  => $admin->id,
            ]);
        });

        $this->forgetCache($type);

        CommissionConfigUpdated::dispatch($config, $admin, 'created', $previous);

        return $config;
    }

    /**
     * Activate a specific config, deactivating any current active one of the same type.
     *
     * @throws \Throwable
     */
    public function activate(CommissionConfig $config, Admin $admin): CommissionConfig
    {
        $previous = null;

        $result = DB::transaction(function () use ($config, &$previous): CommissionConfig {
            $previous = CommissionConfig::active()
                ->byType($config->type)
                ->where('id', '!=', $config->id)
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                $previous->update(['is_active' => false]);
            }

            $fresh = CommissionConfig::lockForUpdate()->findOrFail($config->id);
            $fresh->update(['is_active' => true]);

            return $fresh;
        });

        $this->forgetCache($config->type);

        CommissionConfigUpdated::dispatch($result, $admin, 'activated', $previous);

        return $result;
    }

    /**
     * Deactivate a config. No replacement is set — the effective rate becomes 0.
     *
     * @throws \Throwable
     */
    public function deactivate(CommissionConfig $config, Admin $admin): CommissionConfig
    {
        $result = DB::transaction(function () use ($config): CommissionConfig {
            $fresh = CommissionConfig::lockForUpdate()->findOrFail($config->id);
            $fresh->update(['is_active' => false]);
            return $fresh->refresh();
        });

        $this->forgetCache($config->type);

        CommissionConfigUpdated::dispatch($result, $admin, 'deactivated', null);

        return $result;
    }

    /**
     * Return all configs for a type ordered by created_at DESC.
     *
     * @return Collection<int, CommissionConfig>
     */
    public function getHistory(string $type): Collection
    {
        return CommissionConfig::byType($type)
            ->with('createdBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function cacheKey(string $type): string
    {
        return "commission_config:{$type}";
    }

    private function forgetCache(string $type): void
    {
        try {
            Cache::forget($this->cacheKey($type));
        } catch (\Throwable) {
            // Cache unavailable — no action needed; next read will query DB
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertValidValue(float $value): void
    {
        if ($value < self::VALUE_MIN || $value > self::VALUE_MAX) {
            throw new \InvalidArgumentException(
                "El valor de la comisión debe estar entre "
                . self::VALUE_MIN . " y " . self::VALUE_MAX
                . ". Valor recibido: {$value}"
            );
        }
    }
}
