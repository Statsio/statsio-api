<?php

namespace App\Domain\Identity\Enums;

/**
 * Statuts d'une session de vérification Didit (v3). Libellés exacts renvoyés par
 * l'API et les webhooks — sensibles à la casse et aux espaces.
 *
 * @see https://docs.didit.me/integration/verification-statuses
 */
enum IdentityVerificationStatusEnum: string
{
    case NotStarted = 'Not Started';
    case InProgress = 'In Progress';
    case Approved = 'Approved';
    case Declined = 'Declined';
    case InReview = 'In Review';
    case AwaitingUser = 'Awaiting User';
    case Resubmitted = 'Resubmitted';
    case Expired = 'Expired';
    case Abandoned = 'Abandoned';
    case KycExpired = 'Kyc Expired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public static function tryFromLabel(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }

    /** Le compte est considéré vérifié uniquement dans cet état. */
    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    /** Plus aucune évolution attendue sans action de l'utilisateur. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Declined, self::Expired, self::Abandoned, self::KycExpired], true);
    }

    /** Session ouverte, en attente de l'utilisateur ou d'un traitement Didit. */
    public function isPending(): bool
    {
        return ! $this->isTerminal();
    }
}
