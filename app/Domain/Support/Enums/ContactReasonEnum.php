<?php

namespace App\Domain\Support\Enums;

enum ContactReasonEnum: string
{
    case GENERAL = 'general';
    case PARTENARIAT = 'partenariat';
    case PRESSE = 'presse';
    case COMMERCIAL = 'commercial';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Question générale',
            self::PARTENARIAT => 'Demande de partenariat',
            self::PRESSE => 'Demande presse',
            self::COMMERCIAL => 'Demande commerciale',
        };
    }
}
