<?php

namespace App\Filament\Resources\SourceProvenances;

use App\Filament\Resources\SourceProvenances\Pages\CreateSourceProvenance;
use App\Filament\Resources\SourceProvenances\Pages\EditSourceProvenance;
use App\Filament\Resources\SourceProvenances\Pages\ListSourceProvenances;
use App\Filament\Resources\SourceProvenances\Schemas\SourceProvenanceForm;
use App\Filament\Resources\SourceProvenances\Tables\SourceProvenancesTable;
use App\Models\DataIngestion\SourceProvenance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SourceProvenanceResource extends Resource
{
    protected static ?string $model = SourceProvenance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Provenances de sources';

    protected static ?string $modelLabel = 'provenance';

    protected static ?string $pluralModelLabel = 'provenances';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SourceProvenanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceProvenancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceProvenances::route('/'),
            'create' => CreateSourceProvenance::route('/create'),
            'edit' => EditSourceProvenance::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('dataSources');
    }
}
