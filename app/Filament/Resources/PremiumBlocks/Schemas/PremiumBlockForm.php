<?php

namespace App\Filament\Resources\PremiumBlocks\Schemas;

use App\Domain\Ai\BlockCatalog\StudioBlockCatalog;
use App\Domain\Content\Support\PremiumLimits;
use App\Models\PremiumBlockType;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class PremiumBlockForm
{
    /**
     * Classification premium/freemium des blocs — le catalogue (source de vérité
     * partagée avec l'assistant IA et miroir de statsio-front/app/types/studio.ts)
     * fournit la liste des types ; on ne propose que ceux pas déjà marqués premium.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('block_type')
                    ->label('Bloc du Studio')
                    ->helperText('Seuls les blocs pas encore marqués premium sont proposés.')
                    ->options(self::availableOptions())
                    ->searchable()
                    ->native(false)
                    ->required()
                    // Défense en profondeur : les options excluent déjà les blocs premium existants.
                    ->unique(ignoreRecord: true),

                Select::make('offer_id')
                    ->label('Offre requise')
                    ->helperText('Offre du CRUD « Offres » à partir de laquelle ce bloc est débloqué.')
                    ->relationship('offer', 'name', fn ($query) => $query->orderBy('position'))
                    ->default(fn () => PremiumLimits::paidOffer()?->id)
                    ->native(false)
                    ->required(),
            ]);
    }

    /** @return array<string, string> block_type => "Libellé (catégorie)" */
    private static function availableOptions(): array
    {
        $catalog = app(StudioBlockCatalog::class);
        $premium = PremiumBlockType::types();

        $options = [];
        foreach ($catalog->all() as $type => $meta) {
            if (in_array($type, $premium, true)) {
                continue;
            }
            $options[$type] = "{$meta['label']} ({$meta['category']})";
        }

        return $options;
    }
}
