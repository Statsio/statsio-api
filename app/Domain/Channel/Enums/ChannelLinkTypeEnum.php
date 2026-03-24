<?php

namespace App\Domain\Channel\Enums;

enum ChannelLinkTypeEnum: string
{
    case WEBSITE = 'website';

    case TWITTER = 'twitter';
    case INSTAGRAM = 'instagram';
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case LINKEDIN = 'linkedin';
    case FACEBOOK = 'facebook';
    case DISCORD = 'discord';
    case TELEGRAM = 'telegram';
    case WHATSAPP = 'whatsapp';

    case EMAIL = 'email';
    case PHONE = 'phone';
    case GITHUB = 'github';
    case BUY_ME_A_COFFEE = 'buy_me_a_coffee';
    case SHOP = 'shop';
    case BOOKING = 'booking';
    case OTHER = 'other';

    /**
     * Get the display name for the link type
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::WEBSITE => 'Site Web',
            self::TWITTER => 'Twitter',
            self::INSTAGRAM => 'Instagram',
            self::YOUTUBE => 'YouTube',
            self::TIKTOK => 'TikTok',
            self::LINKEDIN => 'LinkedIn',
            self::FACEBOOK => 'Facebook',
            self::DISCORD => 'Discord',
            self::TELEGRAM => 'Telegram',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Email',
            self::PHONE => 'Téléphone',
            self::GITHUB => 'GitHub',
            self::BUY_ME_A_COFFEE => 'Buy Me a Coffee',
            self::SHOP => 'Boutique',
            self::BOOKING => 'Réservation',
            self::OTHER => 'Autre',
        };
    }

    /**
     * Get the icon name for the link type
     */
    public function getIcon(): string
    {
        return match($this) {
            self::WEBSITE => 'globe',
            self::TWITTER => 'twitter',
            self::INSTAGRAM => 'instagram',
            self::YOUTUBE => 'youtube',
            self::TIKTOK => 'music',
            self::LINKEDIN => 'linkedin',
            self::FACEBOOK => 'facebook',
            self::DISCORD => 'message-circle',
            self::TELEGRAM => 'send',
            self::WHATSAPP => 'message-square',
            self::EMAIL => 'mail',
            self::PHONE => 'phone',
            self::GITHUB => 'github',
            self::BUY_ME_A_COFFEE => 'coffee',
            self::SHOP => 'shopping-cart',
            self::BOOKING => 'calendar',
            self::OTHER => 'link',
        };
    }

    /**
     * Get URL pattern validation for the link type
     */
    public function getUrlPattern(): ?string
    {
        return match($this) {
            self::EMAIL => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
            self::PHONE => '^[\+]?[1-9][\d]{0,15}$',
            self::WEBSITE, self::TWITTER, self::INSTAGRAM, self::YOUTUBE, self::TIKTOK,
            self::LINKEDIN, self::FACEBOOK, self::DISCORD, self::TELEGRAM, self::GITHUB,
            self::BUY_ME_A_COFFEE, self::SHOP, self::BOOKING => '^https?:\/\/.+',
            self::OTHER => null,
        };
    }

    /**
     * Check if the link type requires HTTPS
     */
    public function requiresHttps(): bool
    {
        return match($this) {
            self::EMAIL, self::PHONE, self::OTHER => false,
            default => true,
        };
    }

    /**
     * Get all social media types
     */
    public static function getSocialMediaTypes(): array
    {
        return [
            self::TWITTER,
            self::INSTAGRAM,
            self::YOUTUBE,
            self::TIKTOK,
            self::LINKEDIN,
            self::FACEBOOK,
            self::DISCORD,
            self::TELEGRAM,
        ];
    }

    /**
     * Get all monetization types
     */
    public static function getMonetizationTypes(): array
    {
        return [
            self::BUY_ME_A_COFFEE,
            self::SHOP,
        ];
    }

    /**
     * Get all contact types
     */
    public static function getContactTypes(): array
    {
        return [
            self::EMAIL,
            self::PHONE,
            self::WHATSAPP,
            self::TELEGRAM,
        ];
    }
}
