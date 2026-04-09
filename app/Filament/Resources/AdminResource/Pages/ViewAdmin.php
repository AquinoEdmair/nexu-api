<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Models\Admin;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ViewAdmin extends ViewRecord
{
    protected static string $resource = AdminResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Información')
                ->schema([
                    TextEntry::make('id')
                        ->label('ID')
                        ->copyable(),

                    TextEntry::make('name')
                        ->label('Nombre'),

                    TextEntry::make('email')
                        ->label('Email')
                        ->copyable(),

                    TextEntry::make('role')
                        ->label('Rol')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'super_admin' => 'Super Admin',
                            'manager'     => 'Manager',
                            default       => $state,
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'super_admin' => 'danger',
                            'manager'     => 'info',
                            default       => 'gray',
                        }),

                    TextEntry::make('created_at')
                        ->label('Creado')
                        ->dateTime('d/m/Y H:i'),
                ])
                ->columns(2),

            Section::make('Seguridad')
                ->schema([
                    IconEntry::make('two_factor_confirmed_at')
                        ->label('2FA habilitado')
                        ->boolean()
                        ->trueIcon('heroicon-o-shield-check')
                        ->falseIcon('heroicon-o-shield-exclamation')
                        ->trueColor('success')
                        ->falseColor('gray')
                        ->getStateUsing(fn (Admin $record): bool => $record->two_factor_confirmed_at !== null),

                    TextEntry::make('two_factor_confirmed_at')
                        ->label('2FA activo desde')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('No activado'),

                    TextEntry::make('last_login_at')
                        ->label('Último acceso')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('Nunca'),

                    TextEntry::make('last_login_ip')
                        ->label('Última IP')
                        ->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }

    /** @return array<\Filament\Actions\Action|\Filament\Actions\ActionGroup> */
    protected function getHeaderActions(): array
    {
        /** @var Admin $record */
        $record = $this->getRecord();

        return [
            Action::make('edit')
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() === true)
                ->url(fn (): string => AdminResource::getUrl('edit', ['record' => $record])),

            Action::make('reset_2fa')
                ->label('Deshabilitar 2FA')
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning')
                ->visible(
                    fn (): bool => $record->hasTwoFactorEnabled()
                        && $record->id !== auth()->id()
                        && auth()->user()?->isSuperAdmin() === true
                )
                ->requiresConfirmation()
                ->modalHeading('Deshabilitar 2FA')
                ->modalDescription('Esto eliminará la configuración 2FA de este administrador. ¿Confirmar?')
                ->action(function () use ($record): void {
                    $record->update([
                        'two_factor_secret'         => null,
                        'two_factor_confirmed_at'   => null,
                        'two_factor_recovery_codes' => null,
                    ]);

                    activity()->causedBy(auth()->user())
                        ->performedOn($record)
                        ->log(
                            'Super admin ' . auth()->user()?->name . ' deshabilitó 2FA para ' . $record->email
                        );

                    Notification::make()
                        ->title('2FA deshabilitado correctamente')
                        ->success()
                        ->send();

                    $this->refreshFormData(['two_factor_confirmed_at']);
                }),

            Action::make('reset_password')
                ->label('Resetear contraseña')
                ->icon('heroicon-o-key')
                ->color('danger')
                ->visible(
                    fn (): bool => $record->id !== auth()->id()
                        && auth()->user()?->isSuperAdmin() === true
                )
                ->requiresConfirmation()
                ->modalHeading('Resetear contraseña')
                ->modalDescription('Se generará una nueva contraseña temporal para este administrador. ¿Confirmar?')
                ->action(function () use ($record): void {
                    $tempPassword = Str::random(16);

                    $record->update([
                        'password' => Hash::make($tempPassword, ['rounds' => Admin::BCRYPT_ROUNDS]),
                    ]);

                    activity()->causedBy(auth()->user())
                        ->performedOn($record)
                        ->log(
                            'Super admin ' . auth()->user()?->name . ' reseteó contraseña de ' . $record->email
                        );

                    Notification::make()
                        ->title('Contraseña reseteada')
                        ->body("Nueva contraseña temporal: <strong>{$tempPassword}</strong><br>Guárdala ahora — no se volverá a mostrar.")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
