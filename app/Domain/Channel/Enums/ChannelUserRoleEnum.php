<?php

namespace App\Domain\Channel\Enums;

enum ChannelUserRoleEnum: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MODERATOR = 'moderator';
    case SUBSCRIBER = 'subscriber';

    /**
     * Get the display name for the role
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Administrateur',
            self::MODERATOR => 'Modérateur',
            self::SUBSCRIBER => 'Abonné',
        };
    }

    /**
     * Get the permission level (higher number = more permissions)
     */
    public function getPermissionLevel(): int
    {
        return match($this) {
            self::OWNER => 100,
            self::ADMIN => 80,
            self::MODERATOR => 60,
            self::SUBSCRIBER => 20,
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
     * Get all management roles
     */
    public static function getManagementRoles(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::MODERATOR,
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
            self::MODERATOR,
            self::SUBSCRIBER,
        ];
    }
}
