<?php

namespace App\Services\Maladies;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Enrichissement optionnel : symptômes et facteurs de risque via l'API UMLS/UTS du NLM
 * (uts-ws.nlm.nih.gov), pour compléter la classification ICD-11 qui n'a pas de champ structuré
 * pour ça. UMLS est un agrégateur de terminologies (SNOMED CT, MeSH, MedDRA...), pas lui-même
 * la source du fait clinique : chaque relation renvoyée porte son terminology d'origine dans
 * `source`, jamais juste un libellé nu.
 *
 * Nécessite une clé UMLS_API_KEY séparée (compte UTS gratuit sur uts.nlm.nih.gov/uts/signup-login,
 * non fournie par défaut). Sans clé, ou si le code ICD-11 n'a pas de correspondance UMLS
 * (couverture ICD-11 côté UMLS encore partielle), retourne null sans erreur — dégradation
 * gracieuse identique à WhoGhoApiClient::getFrancePrevalence, ce module n'est jamais bloquant.
 */
class UmlsApiClient
{
    private const CACHE_TTL_DAYS = 7;

    /** Relations UMLS jugées pertinentes comme "symptôme / signe clinique". */
    private const SYMPTOM_RELATION_LABELS = [
        'associated_finding', 'has_finding', 'has_manifestation', 'may_be_finding_of',
        'due_to', 'has_associated_finding',
    ];

    /** Relations UMLS jugées pertinentes comme "facteur de risque". */
    private const RISK_FACTOR_RELATION_LABELS = [
        'has_causative_agent', 'has_risk_factor', 'associated_with', 'occurs_after',
    ];

    /** @return array{symptoms: array{label: string, source: string}[], riskFactors: array{label: string, source: string}[]}|null */
    public function getSymptomsAndRiskFactors(string $icd11Code): ?array
    {
        $apiKey = config('services.umls_api.key');
        if (! $apiKey) {
            return null;
        }

        return Cache::remember(
            "umls:symptoms:{$icd11Code}",
            now()->addDays(self::CACHE_TTL_DAYS),
            function () use ($icd11Code, $apiKey) {
                $cui = $this->crosswalkToCui($icd11Code, $apiKey);
                if ($cui === null) {
                    return null;
                }

                return $this->relationsFor($cui, $apiKey);
            },
        );
    }

    private function crosswalkToCui(string $icd11Code, string $apiKey): ?string
    {
        try {
            $response = Http::timeout(10)->get(
                config('services.umls_api.base_url').'/crosswalk/current/source/ICD11/'.$icd11Code,
                ['apiKey' => $apiKey],
            );
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return $response->json('result.0.ui');
    }

    /** @return array{symptoms: array{label: string, source: string}[], riskFactors: array{label: string, source: string}[]}|null */
    private function relationsFor(string $cui, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(10)->get(
                config('services.umls_api.base_url')."/content/current/CUI/{$cui}/relations",
                ['apiKey' => $apiKey, 'pageSize' => 100],
            );
        } catch (ConnectionException|RequestException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $relations = collect($response->json('result') ?? []);

        $toEntry = fn (array $rel) => [
            'label' => $rel['relatedIdName'] ?? null,
            'source' => 'UMLS ('.($rel['rootSource'] ?? 'terminologie source').')',
        ];

        $matches = fn (array $rel, array $labels) => in_array(
            mb_strtolower((string) ($rel['additionalRelationLabel'] ?? $rel['relationLabel'] ?? '')),
            $labels,
            true,
        );

        $symptoms = $relations
            ->filter(fn (array $rel) => $matches($rel, self::SYMPTOM_RELATION_LABELS) && ! empty($rel['relatedIdName']))
            ->map($toEntry)
            ->unique('label')
            ->values()
            ->all();

        $riskFactors = $relations
            ->filter(fn (array $rel) => $matches($rel, self::RISK_FACTOR_RELATION_LABELS) && ! empty($rel['relatedIdName']))
            ->map($toEntry)
            ->unique('label')
            ->values()
            ->all();

        if ($symptoms === [] && $riskFactors === []) {
            return null;
        }

        return ['symptoms' => $symptoms, 'riskFactors' => $riskFactors];
    }
}
