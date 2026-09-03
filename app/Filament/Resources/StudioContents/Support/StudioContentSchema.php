<?php

namespace App\Filament\Resources\StudioContents\Support;

use App\Domain\Content\Enums\ContentCoverageEnum;
use App\Domain\Content\Enums\SubBrandEnum;
use App\Domain\Content\Enums\SurveyKindEnum;
use App\Models\Channel\Channel;
use App\Models\Content\ContentCategory;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

                Select::make('status')
                    ->label('Statut')
                    ->options([
                        'draft' => 'Brouillon',
                        'published' => 'Publié',
                    ])
                    ->default('draft')
                    ->required(),

                Select::make('sub_brand')
                    ->label('Domaine')
                    ->helperText('Sous-marque de publication du contenu. Fige les catégories proposées.')
                    ->options(SubBrandEnum::contentOptions())
                    ->default(SubBrandEnum::Statsio->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        $allowed = ContentCategory::query()->forSubBrand($state)->pluck('slug')->all();
                        $set('categories', array_values(array_intersect((array) $get('categories'), $allowed)));
                    }),

                Select::make('categories')
                    ->label('Catégories')
                    ->helperText('Limitées au domaine sélectionné (+ « toutes les marques »).')
                    ->multiple()
                    ->options(fn (Get $get): array => ContentCategory::query()
                        ->forSubBrand($get('sub_brand'))
                        ->orderBy('position')
                        ->pluck('name', 'slug')
                        ->all())
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

                Toggle::make('is_featured')
                    ->label('À la une')
                    ->helperText('Épingle ce contenu en tête du listing public de son type, avec le badge « À LA UNE ».')
                    ->live(),
                TextInput::make('featured_priority')
                    ->label('Priorité à la une')
                    ->helperText('Plus petit = affiché en premier. Le n°1 devient la grande card mise en avant ; les suivants sont remontés en tête de grille.')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->visible(fn (Get $get): bool => (bool) $get('is_featured'))
                    ->required(fn (Get $get): bool => (bool) $get('is_featured')),

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
            Select::make('coverage')
                ->label('Couverture géographique')
                ->options(ContentCoverageEnum::options()),
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
