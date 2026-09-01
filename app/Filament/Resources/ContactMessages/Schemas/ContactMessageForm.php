<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use App\Domain\Support\Enums\ContactMessageStatusEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nom')->disabled()->dehydrated(false),
                TextInput::make('email')->label('Email')->disabled()->dehydrated(false),
                TextInput::make('company')->label('Société')->disabled()->dehydrated(false),
                TextInput::make('reason')->label('Motif')->disabled()->dehydrated(false),
                Textarea::make('message')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->rows(8),
                Select::make('status')
                    ->label('Statut')
                    ->options([
                        ContactMessageStatusEnum::NEW->value => 'Nouveau',
                        ContactMessageStatusEnum::IN_PROGRESS->value => 'En cours',
                        ContactMessageStatusEnum::RESOLVED->value => 'Résolu',
                    ])
                    ->required(),
            ]);
    }
}
