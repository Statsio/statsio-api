<?php

namespace App\Filament\Resources\StudioContents\Support;

use App\Domain\Content\Enums\SurveyKindEnum;
use App\Models\Channel\Channel;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StudioContentSchema
{
    public static function configure(Schema $schema, string $type): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(2000)
                    ->columnSpanFull(),
                TextInput::make('emoji')
                    ->label('Emoji')
                    ->maxLength(16),

                Select::make('status')
                    ->label('Statut')
                    ->options([
                        'draft' => 'Brouillon',
                        'published' => 'Publié',
                    ])
                    ->default('draft')
                    ->required(),
                Select::make('visibility')
                    ->label('Visibilité')
                    ->options([
                        'public' => 'Public',
                        'protege' => 'Protégé',
                        'private' => 'Privé',
                    ])
                    ->default('private')
                    ->required(),

                TagsInput::make('categories')
                    ->label('Catégories')
                    ->columnSpanFull(),

                Select::make('published_as')
                    ->label('Publié au nom de')
                    ->options([
                        'user' => 'Un utilisateur',
                        'channel' => 'Une chaîne',
                    ])
                    ->live(),
                Select::make('channel_id')
                    ->label('Chaîne')
                    ->relationship('channel', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Channel $record): string => $record->profile?->name ?? "Chaîne #{$record->id}")
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('published_as') === 'channel'),

                ...self::coverageFields($type),
                ...self::surveyFields($type),
            ]);
    }

    /** @return array<int, Component> */
    private static function coverageFields(string $type): array
    {
        if ($type !== 'statsdata') {
            return [];
        }

        return [
            Select::make('coverage_type')
                ->label('Couverture géographique')
                ->options([
                    'monde' => 'Monde',
                    'pays' => 'Pays',
                    'ville' => 'Ville',
                ]),
        ];
    }

    /** @return array<int, Component> */
    private static function surveyFields(string $type): array
    {
        if ($type !== 'survey') {
            return [];
        }

        return [
            Select::make('survey_kind')
                ->label('Type de consultation')
                ->options(collect(SurveyKindEnum::cases())
                    ->mapWithKeys(fn (SurveyKindEnum $c): array => [$c->value => $c->label()])
                    ->all())
                ->default(SurveyKindEnum::SingleQuestion->value)
                ->live()
                ->required(),
            DateTimePicker::make('response_deadline')
                ->label('Date limite de réponse'),
            Toggle::make('requires_identity_verification')
                ->label('Vérification d\'identité requise'),
            TextInput::make('petition_goal')
                ->label('Objectif de signatures')
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get): bool => $get('survey_kind') === SurveyKindEnum::Petition->value),
            TextInput::make('petition_target')
                ->label('Destinataire de la pétition')
                ->maxLength(2000)
                ->visible(fn (Get $get): bool => $get('survey_kind') === SurveyKindEnum::Petition->value),
        ];
    }
}
