<?php

namespace App\Filament\Resources\StudioContents;

use App\Filament\Resources\StudioContents\Support\StudioContentSchema;
use App\Filament\Resources\StudioContents\Support\StudioContentTable;
use App\Models\StudioContent;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base commune aux ressources de contenus Studio (Articles / Statsdata /
 * Sondages). Chaque ressource concrète ne fait que fixer son `type` ; le
 * formulaire et le tableau sont mutualisés.
 *
 * Le panneau étant déjà réservé aux admins plateforme (`canAccessPanel`), on
 * court-circuite la StudioContentPolicy (qui ne couvre que `update` pour les
 * besoins du Studio / de l'assistant IA).
 */
abstract class AbstractStudioContentResource extends Resource
{
    protected static ?string $model = StudioContent::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $recordTitleAttribute = 'title';

    /** Valeur de la colonne `studio_contents.type` gérée par la ressource. */
    abstract public static function contentType(): string;

    public static function form(Schema $schema): Schema
    {
        return StudioContentSchema::configure($schema, static::contentType());
    }

    public static function table(Table $table): Table
    {
        return StudioContentTable::configure($table, static::contentType());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', static::contentType())
            ->with(['user.profile', 'channel.profile']);
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }
}
