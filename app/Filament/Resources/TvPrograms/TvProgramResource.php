<?php

namespace App\Filament\Resources\TvPrograms;

use App\Filament\Resources\TvPrograms\Pages\EditTvProgram;
use App\Filament\Resources\TvPrograms\Pages\ListTvPrograms;
use App\Filament\Resources\TvPrograms\Schemas\TvProgramForm;
use App\Filament\Resources\TvPrograms\Tables\TvProgramsTable;
use App\Models\Tv\TvProgram;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TvProgramResource extends Resource
{
    protected static ?string $model = TvProgram::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-film';

    protected static string|\UnitEnum|null $navigationGroup = 'TV';

    protected static ?string $navigationLabel = 'Programmes';

    protected static ?string $modelLabel = 'programme';

    protected static ?string $pluralModelLabel = 'programmes';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TvProgramForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TvProgramsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTvPrograms::route('/'),
            'edit' => EditTvProgram::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('broadcasts')
            ->with('categories:id,name,slug,color');
    }
}
