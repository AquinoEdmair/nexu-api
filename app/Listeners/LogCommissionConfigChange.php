<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommissionConfigUpdated;

final class LogCommissionConfigChange
{
    public function handle(CommissionConfigUpdated $event): void
    {
        $typeLabel = $event->config->type === 'deposit' ? 'depósito' : 'referido';
        $newValue  = number_format((float) $event->config->value, 4);
        $oldValue  = $event->previousConfig !== null
            ? number_format((float) $event->previousConfig->value, 4)
            : null;

        $description = match ($event->action) {
            'created' => $oldValue !== null
                ? "Admin {$event->admin->name} creó config {$typeLabel}: {$newValue}% (reemplaza anterior: {$oldValue}%)"
                : "Admin {$event->admin->name} creó config {$typeLabel}: {$newValue}% (sin config anterior)",
            'activated'   => "Admin {$event->admin->name} activó config {$event->config->id} ({$typeLabel}: {$newValue}%)",
            'deactivated' => "Admin {$event->admin->name} desactivó config {$event->config->id} ({$typeLabel}: {$newValue}%)",
            default       => "Admin {$event->admin->name} modificó config {$typeLabel}",
        };

        activity('commissions')
            ->causedBy($event->admin)
            ->performedOn($event->config)
            ->withProperties([
                'config_id' => $event->config->id,
                'type'      => $event->config->type,
                'new_value' => (float) $event->config->value,
                'old_value' => $event->previousConfig !== null ? (float) $event->previousConfig->value : null,
                'action'    => $event->action,
                'admin_id'  => $event->admin->id,
            ])
            ->log($description);
    }
}
