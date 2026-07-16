<?php

namespace App\Services\Maladies;

/**
 * Maladies suivies pour l'enrichissement chiffré (grille "populaires" de la page Maladies +
 * classement "maladies principales" d'un pays). Identifiées par leur id de linéarisation ICD-11
 * numérique (voir Icd11ApiClient) — code clinique en commentaire pour la lisibilité, résolu une
 * fois via /mms/codeinfo/{code} lors de la curation de cette liste, pas à chaque requête.
 *
 * Indicateur GHO vérifié individuellement pour chaque maladie (voir le plan d'implémentation) ;
 * `null` = pas de couverture GHO trouvée (ex. l'OMS ne publie pas d'incidence par site de cancer,
 * ce n'est pas son périmètre). La fiche affiche alors la classification ICD-11 seule, sans
 * panneaux stats — dégradation gracieuse, pas une erreur.
 */
class TrackedDiseases
{
    private const DISEASES = [
        '119724091' => ['code' => '5A11', 'indicatorCode' => 'NCD_DIABETES_PREVALENCE_AGESTD', 'unit' => '% de la population'], // Diabète de type 2
        '882244568' => ['code' => '1B10', 'indicatorCode' => 'MDG_0000000020', 'unit' => 'pour 100 000 hab./an'], // Tuberculose (incidence /100k/an)
        '579583286' => ['code' => '1F40', 'indicatorCode' => 'MALARIA_EST_INCIDENCE', 'unit' => 'pour 1000 pop. à risque'], // Paludisme (incidence /1000 à risque)
        '1508081745' => ['code' => '1C62', 'indicatorCode' => 'HIV_0000000001', 'unit' => 'personnes'], // VIH/Sida (personnes vivant avec le VIH)
        '761947693' => ['code' => 'BA00', 'indicatorCode' => 'NCD_HYP_PREVALENCE_A', 'unit' => '% de la population'], // Hypertension artérielle
        '578635574' => ['code' => '6A70', 'indicatorCode' => 'GDO_q35', 'unit' => '% de la population'], // Trouble dépressif majeur
        '1826431497' => ['code' => '1F03', 'indicatorCode' => 'WHS3_62', 'unit' => 'cas notifiés'], // Rougeole (cas notifiés)
        '316539081' => ['code' => '2C25', 'indicatorCode' => null, 'unit' => null], // Cancer du poumon — pas d'indicateur GHO
    ];

    public static function indicatorFor(string $id): ?string
    {
        return self::DISEASES[$id]['indicatorCode'] ?? null;
    }

    public static function unitFor(string $id): ?string
    {
        return self::DISEASES[$id]['unit'] ?? null;
    }

    public static function isTracked(string $id): bool
    {
        return array_key_exists($id, self::DISEASES);
    }

    /** @return string[] Ids des maladies suivies, pour la grille "populaires" de la page liste. */
    public static function ids(): array
    {
        return array_keys(self::DISEASES);
    }

    /** @return array<string, string> id => indicatorCode, seulement les maladies couvertes par GHO. */
    public static function trackedIndicators(): array
    {
        return collect(self::DISEASES)
            ->filter(fn (array $d) => $d['indicatorCode'] !== null)
            ->map(fn (array $d) => $d['indicatorCode'])
            ->all();
    }
}
