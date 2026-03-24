<?php

namespace App\Domain\Channel\Enums;

enum ChannelAgeRestrictionEnum: int
{
    case ALL_AGES = 0;
    case PG_13 = 13;
    case PG_16 = 16;
    case PG_18 = 18;

    /**
     * Get the display name for the age restriction
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::ALL_AGES => 'Tous publics',
            self::PG_13 => '-13 ans',
            self::PG_16 => '-16 ans',
            self::PG_18 => '-18 ans',
        };
    }

    /**
     * Get the description for the age restriction
     */
    public function getDescription(): string
    {
        return match($this) {
            self::ALL_AGES => 'Convient à tous les âges',
            self::PG_13 => 'Déconseillé aux moins de 13 ans',
            self::PG_16 => 'Déconseillé aux moins de 16 ans',
            self::PG_18 => 'Déconseillé aux moins de 18 ans',
        };
    }

    /**
     * Get the warning color for UI
     */
    public function getColor(): string
    {
        return match($this) {
            self::ALL_AGES => 'green',
            self::PG_13 => 'yellow',
            self::PG_16 => 'orange',
            self::PG_18 => 'red',
        };
    }

    /**
     * Get the icon for the age restriction
     */
    public function getIcon(): string
    {
        return match($this) {
            self::ALL_AGES => 'check-circle',
            self::PG_13 => 'alert-circle',
            self::PG_16 => 'alert-triangle',
            self::PG_18 => 'shield-alert',
        };
    }

    /**
     * Check if content is suitable for a given age
     */
    public function isSuitableFor(int $age): bool
    {
        return $age >= $this->value;
    }

    /**
     * Get all age restrictions as options for forms
     */
    public static function getOptions(): array
    {
        return [
            ['value' => self::ALL_AGES->value, 'label' => self::ALL_AGES->getDisplayName()],
            ['value' => self::PG_13->value, 'label' => self::PG_13->getDisplayName()],
            ['value' => self::PG_16->value, 'label' => self::PG_16->getDisplayName()],
            ['value' => self::PG_18->value, 'label' => self::PG_18->getDisplayName()],
        ];
    }

    /**
     * Get age restriction from integer value
     */
    public static function fromValue(int $value): self
    {
        return match($value) {
            0 => self::ALL_AGES,
            13 => self::PG_13,
            16 => self::PG_16,
            18 => self::PG_18,
            default => self::ALL_AGES,
        };
    }

    /**
     * Check if this is adult content (18+)
     */
    public function isAdultContent(): bool
    {
        return $this === self::PG_18;
    }

    /**
     * Check if this is strictly adult content (21+ only)
     */
    public function isStrictlyAdultContent(): bool
    {
        return false; // Plus de restriction 21+
    }

    /**
     * Check if this is restricted content
     */
    public function isRestricted(): bool
    {
        return $this !== self::ALL_AGES;
    }

    /**
     * Check if user is considered adult (18+)
     */
    public function isUserAdult(int $age): bool
    {
        return $age >= 18;
    }

    /**
     * Check if user can access this content
     */
    public function canUserAccess(int $userAge): bool
    {
        // Si le contenu est pour tous publics, tout le monde peut accéder
        if ($this === self::ALL_AGES) {
            return true;
        }

        // Pour les autres restrictions, vérifier l'âge minimum
        return $userAge >= $this->value;
    }

    /**
     * Get access requirement description
     */
    public function getAccessRequirement(): string
    {
        return match($this) {
            self::ALL_AGES => 'Aucune restriction',
            self::PG_13 => '13 ans et plus',
            self::PG_16 => '16 ans et plus',
            self::PG_18 => '18 ans et plus (majeur)',
        };
    }
}
