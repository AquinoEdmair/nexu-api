<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        return [
            EditAction::make(),

            Action::make('block')
                ->label('Bloquear usuario')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn(): bool => Gate::allows('block', $user))
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo del bloqueo')
                        ->required()
                        ->minLength(10)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    /** @var Admin $admin */
                    $admin = auth()->user();
                    app(UserService::class)->updateStatus($record, 'blocked', $data['reason'], $admin);
                    $this->redirect(static::getUrl(['record' => $record]));
                }),

            Action::make('unblock')
                ->label('Desbloquear usuario')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn(): bool => Gate::allows('unblock', $user))
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo de reactivación')
                        ->required()
                        ->minLength(5)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    /** @var Admin $admin */
                    $admin = auth()->user();
                    app(UserService::class)->updateStatus($record, 'active', $data['reason'], $admin);
                    $this->redirect(static::getUrl(['record' => $record]));
                }),

            Action::make('resetPassword')
                ->label('Resetear contraseña')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn(): bool => Gate::allows('resetPassword', $user))
                ->requiresConfirmation()
                ->modalHeading('Resetear contraseña')
                ->modalDescription('Se generará una contraseña temporal y se enviará al correo del usuario.')
                ->action(function (): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    /** @var Admin $admin */
                    $admin = auth()->user();
                    app(UserService::class)->resetPassword($record, $admin);
                }),
        ];
    }
}
