<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\User\Enums\UserStatusEnum;
use App\Models\User\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('status')
                    ->label('Statut')
                    ->options([
                        UserStatusEnum::ACTIVE->value => 'Actif',
                        UserStatusEnum::SUSPENDED->value => 'Suspendu',
                        UserStatusEnum::BANNED->value => 'Banni',
                    ])
                    ->required(),
                Toggle::make('is_admin')
                    ->label('Administrateur plateforme')
                    ->helperText('Donne accès à ce back-office.')
                    ->disabled(fn (?User $record): bool => $record !== null && $record->id === auth()->id()),
            ]);
    }
}
