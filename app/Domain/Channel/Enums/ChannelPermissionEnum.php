<?php

namespace App\Domain\Channel\Enums;

/**
 * Catalogue fixe des permissions assignables à un membre d'équipe de chaîne.
 * Groupé par catégorie pour l'affichage (voir ChannelController::permissionsCatalog()).
 *
 * La suppression de la chaîne n'est volontairement PAS dans ce catalogue : c'est un
 * contrôle de rôle (owner uniquement), jamais une permission qu'on peut cocher/décocher
 * — voir DeleteChannelRequest.
 */
enum ChannelPermissionEnum: string
{
    case CONTENTS_CREATE = 'contents.create';
    case CONTENTS_EDIT = 'contents.edit';
    case CONTENTS_PUBLISH = 'contents.publish';
    case CONTENTS_DELETE = 'contents.delete';

    case AUDIENCE_VIEW_STATS = 'audience.view_stats';
    case AUDIENCE_MANAGE_SUBSCRIBERS = 'audience.manage_subscribers';

    case TEAM_INVITE_MEMBERS = 'team.invite_members';
    case TEAM_MANAGE_ROLES = 'team.manage_roles';
    case TEAM_REMOVE_MEMBERS = 'team.remove_members';

    case CHANNEL_EDIT_PROFILE = 'channel.edit_profile';
    case CHANNEL_EDIT_APPEARANCE = 'channel.edit_appearance';
    case CHANNEL_EDIT_PRIVACY = 'channel.edit_privacy';

    public function category(): string
    {
        return match ($this) {
            self::CONTENTS_CREATE, self::CONTENTS_EDIT, self::CONTENTS_PUBLISH, self::CONTENTS_DELETE => 'Contenus',
            self::AUDIENCE_VIEW_STATS, self::AUDIENCE_MANAGE_SUBSCRIBERS => 'Audience',
            self::TEAM_INVITE_MEMBERS, self::TEAM_MANAGE_ROLES, self::TEAM_REMOVE_MEMBERS => 'Équipe',
            self::CHANNEL_EDIT_PROFILE, self::CHANNEL_EDIT_APPEARANCE, self::CHANNEL_EDIT_PRIVACY => 'Chaîne',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CONTENTS_CREATE => 'Créer des contenus',
            self::CONTENTS_EDIT => 'Modifier les contenus',
            self::CONTENTS_PUBLISH => 'Publier / dépublier',
            self::CONTENTS_DELETE => 'Supprimer des contenus',
            self::AUDIENCE_VIEW_STATS => 'Voir les statistiques',
            self::AUDIENCE_MANAGE_SUBSCRIBERS => 'Gérer les abonnés',
            self::TEAM_INVITE_MEMBERS => 'Inviter des membres',
            self::TEAM_MANAGE_ROLES => 'Modifier les rôles',
            self::TEAM_REMOVE_MEMBERS => 'Retirer des membres',
            self::CHANNEL_EDIT_PROFILE => 'Modifier le profil',
            self::CHANNEL_EDIT_APPEARANCE => 'Modifier les couleurs',
            self::CHANNEL_EDIT_PRIVACY => 'Modifier la confidentialité',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Catalogue groupé par catégorie, dans l'ordre naturel des cases, pour
     * GET /channels/permissions : [{ category, permissions: [{key, label}] }].
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->category()][] = [
                'key' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        $result = [];
        foreach ($groups as $category => $permissions) {
            $result[] = ['category' => $category, 'permissions' => $permissions];
        }

        return $result;
    }
}
