<?php

declare(strict_types=1);

namespace App\Filament\Resources\YieldLogResource\Pages;

use App\DTOs\ApplyYieldDTO;
use App\Enums\NegativePolicy;
use App\Enums\YieldScope;
use App\Enums\YieldType;
use App\Filament\Resources\YieldLogResource;
use App\Models\Admin;
use App\Models\User;
use App\Services\PreviewYieldService;
use App\Services\YieldService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class CreateYieldLog extends CreateRecord
{
    use HasWizard;

    protected static string $resource = YieldLogResource::class;

    /** @return array<Step> */
    protected function getSteps(): array
    {
        return [
            Step::make('Configurar rendimiento')
                ->description('Define el tipo, valor y alcance.')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Radio::make('type')
                        ->label('Tipo de rendimiento')
                        ->options([
                            'percentage' => 'Porcentaje (%)',
                            'fixed_amount' => 'Monto fijo (USD)',
                        ])
                        ->default('percentage')
                        ->required()
                        ->live(),

                    TextInput::make('value')
                        ->label(fn(Get $get): string => $get('type') === 'fixed_amount' ? 'Monto (USD)' : 'Porcentaje (%)')
                        ->numeric()
                        ->required()
                        ->helperText('Valores negativos aplican una deducción.')
                        ->live(),

                    Radio::make('scope')
                        ->label('Alcance')
                        ->options([
                            'all' => 'Todos los usuarios activos',
                            'specific_user' => 'Usuario específico',
                        ])
                        ->default('all')
                        ->required()
                        ->live(),

                    Select::make('specific_user_id')
                        ->label('Usuario')
                        ->searchable()
                        ->getSearchResultsUsing(
                            fn(string $search): array => User::active()
                                ->where(
                                    fn($q) => $q
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                )
                                ->limit(20)
                                ->pluck('name', 'id')
                                ->toArray()
                        )
                        ->getOptionLabelUsing(
                            fn(?string $value): ?string => $value
                            ? User::find($value)?->name
                            : null
                        )
                        ->visible(fn(Get $get): bool => $get('scope') === 'specific_user')
                        ->required(fn(Get $get): bool => $get('scope') === 'specific_user'),

                    Radio::make('negative_policy')
                        ->label('Política para balance negativo')
                        ->options([
                            'floor' => 'Aplicar hasta $0 (recomendado)',
                            'skip' => 'Omitir usuarios afectados',
                        ])
                        ->default('floor')
                        ->required()
                        ->helperText('Determina qué hacer cuando la deducción supera el saldo del usuario.'),

                    Textarea::make('description')
                        ->label('Descripción (opcional)')
                        ->rows(3)
                        ->maxLength(500),
                ]),

            Step::make('Preview y confirmación')
                ->description('Revisa el impacto antes de confirmar.')
                ->icon('heroicon-o-eye')
                ->schema([
                    Placeholder::make('preview_content')
                        ->label('')
                        ->content(function (Get $get): HtmlString {
                            $type = $get('type');
                            $value = $get('value');
                            $scope = $get('scope') ?? 'all';
                            $userId = $get('specific_user_id');
                            $policy = $get('negative_policy') ?? 'floor';

                            if (empty($type) || $value === null || $value === '') {
                                return new HtmlString(
                                    '<p class="text-gray-500 text-sm">Vuelve al paso anterior y completa los campos requeridos.</p>'
                                );
                            }

                            $dto = new ApplyYieldDTO(
                                type: $type,
                                value: (float) $value,
                                scope: $scope,
                                userId: $userId ?: null,
                                description: $get('description'),
                                negativePolicy: $policy,
                            );

                            $preview = app(PreviewYieldService::class)->calculate($dto);

                            return new HtmlString(
                                view('filament.yield-preview', [
                                    'preview' => $preview,
                                    'policy'  => $policy,
                                ])->render()
                            );
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $dto = new ApplyYieldDTO(
            type: $data['type'],
            value: (float) $data['value'],
            scope: $data['scope'],
            userId: $data['specific_user_id'] ?? null,
            description: $data['description'] ?? null,
            negativePolicy: $data['negative_policy'] ?? 'floor',
        );

        /** @var Admin $admin */
        $admin = auth()->user();

        return app(YieldService::class)->createAndDispatch($dto, $admin);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

}
