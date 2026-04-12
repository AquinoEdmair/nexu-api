<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionConfigResource\Pages;

use App\Filament\Resources\CommissionConfigResource;
use App\Models\Admin;
use App\Services\CommissionService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class CreateCommissionConfig extends CreateRecord
{
    protected static string $resource = CommissionConfigResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Nueva configuración de comisión')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('Tipo de comisión')
                        ->options([
                            'deposit'    => 'Comisión de depósito',
                            'withdrawal' => 'Comisión de retiro',
                        ])
                        ->required()
                        ->native(false),

                    TextInput::make('value')
                        ->label('Valor (%)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(99.99)
                        ->step(0.01)
                        ->suffix('%')
                        ->helperText('Porcentaje aplicado como comisión. Rango: 0.01 – 99.99'),

                    Textarea::make('description')
                        ->label('Descripción (opcional)')
                        ->nullable()
                        ->maxLength(2000)
                        ->placeholder('Ej: Ajuste Q1 2026, Campaña promocional...')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('⚠ Aviso importante')
                ->description(new HtmlString(
                    'Al guardar, la configuración activa actual del tipo seleccionado será '
                    . '<strong>desactivada automáticamente</strong>. '
                    . 'Las transacciones y referidos anteriores <strong>NO se verán afectados</strong> — '
                    . 'la tasa se guarda como snapshot en cada operación.'
                ))
                ->schema([])
                ->collapsible(false),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Admin $admin */
        $admin = auth()->user();

        return app(CommissionService::class)->updateConfig(
            type: $data['type'],
            value: (float) $data['value'],
            description: $data['description'],
            admin: $admin,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
