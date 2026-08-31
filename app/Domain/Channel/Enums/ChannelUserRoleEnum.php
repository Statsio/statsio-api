<?php

namespace App\Domain\Channel\Enums;

enum ChannelUserRoleEnum: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case REDACTOR = 'redactor';
    case GUEST = 'guest';
    case SUBSCRIBER = 'subscriber';

    /**
     * Get the display name for the role
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Administrateur',
            self::REDACTOR => 'Rédacteur',
            self::GUEST => 'Invité',
            self::SUBSCRIBER => 'Abonné',
        };
    }

    /**
     * Get the permission level (higher number = more permissions)
     */
    public function getPermissionLevel(): int
    {
        return match ($this) {
            self::OWNER => 100,
            self::ADMIN => 80,
            self::REDACTOR => 60,
            self::GUEST => 40,
            self::SUBSCRIBER => 10,
        };
    }

    /**
     * Check if role can manage channel
     */
    public function canManageChannel(): bool
    {
        return $this->getPermissionLevel() >= 60;
    }

    /**
     * Check if role can moderate content
     */
    public function canModerate(): bool
    {
        return $this->getPermissionLevel() >= 60;
    }

    /**
     * Check if role is admin or owner
     */
    public function isAdmin(): bool
    {
        return $this->getPermissionLevel() >= 80;
    }

    /**
     * Check if role is owner
     */
    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Permissions cochées par défaut pour ce rôle lors d'une invitation (voir
     * ChannelPermissionEnum). Owner/admin ont toujours le catalogue complet,
     * quoi que le client envoie — cf. ChannelInvitationAction::invite().
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::OWNER, self::ADMIN => array_map(
                fn (ChannelPermissionEnum $p) => $p->value,
                ChannelPermissionEnum::cases(),
            ),
            self::REDACTOR => [
                ChannelPermissionEnum::CONTENTS_CREATE->value,
                ChannelPermissionEnum::CONTENTS_EDIT->value,
                ChannelPermissionEnum::CONTENTS_PUBLISH->value,
                ChannelPermissionEnum::AUDIENCE_VIEW_STATS->value,
            ],
            self::GUEST => [
                ChannelPermissionEnum::CONTENTS_CREATE->value,
                ChannelPermissionEnum::CONTENTS_EDIT->value,
            ],
            self::SUBSCRIBER => [],
        };
    }

    /**
     * Get all management roles (équipe affichée dans le dashboard chaîne — hors abonnés)
     */
    public static function getManagementRoles(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::REDACTOR,
            self::GUEST,
        ];
    }

    /**
     * Get all roles ordered by permission level
     */
    public static function getOrderedRoles(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::REDACTOR,
            self::GUEST,
            self::SUBSCRIBER,
        ];
    }

    /**
     * Rôles assignables via l'invitation de membres (tous sauf abonné, qui n'est
     * pas un rôle "équipe" mais l'état par défaut d'un simple follower).
     */
    public static function getInvitableRoles(): array
    {
        return self::getManagementRoles();
    }
}
