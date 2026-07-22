<?php

namespace App\Services\DataIngestion\Security;

use App\Domain\DataIngestion\Exceptions\SsrfBlockedException;

/**
 * Protection SSRF (OWASP A10) pour toute URL fournie par un créateur de source
 * (source "api" en ingestion classique, ou requêtée en direct pour une source
 * "live" — voir HttpProbeService, qui appelle assertUrlIsSafe() avant chaque
 * requête réelle, y compris pour les URLs "next" renvoyées par l'API distante).
 *
 * Principe : seuls http/https sont autorisés, et l'hôte (littéral ou résolu
 * via DNS) doit être une adresse IP publique — toute plage privée, loopback,
 * lien-local ou autrement réservée (RFC 1918, RFC 3927, etc., pour l'IPv4 et
 * l'IPv6) est rejetée avant l'appel réel.
 */
class SsrfGuard
{
    /**
     * @throws SsrfBlockedException
     */
    public function assertUrlIsSafe(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfBlockedException("Seuls les protocoles http et https sont autorisés (reçu : '{$scheme}').");
        }

        $host = isset($parts['host']) ? trim($parts['host'], '[]') : '';

        if ($host === '') {
            throw new SsrfBlockedException('URL invalide : hôte manquant.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->resolveHost($host);

        if ($ips === []) {
            throw new SsrfBlockedException("Impossible de résoudre l'hôte '{$host}'.");
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new SsrfBlockedException(
                    "L'hôte '{$host}' résout vers une adresse IP privée ou réservée ({$ip}), refusée pour éviter un SSRF."
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $ips = array_map(
            static fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        );

        return array_values(array_unique(array_filter($ips)));
    }

    private function isPublicIp(string $ip): bool
    {
        $binary = @inet_pton($ip);

        if ($binary === false) {
            return false;
        }

        // Une IPv4 mappée en IPv6 (::ffff:a.b.c.d) n'est pas reconnue comme privée par les
        // flags FILTER_FLAG_NO_PRIV_RANGE/NO_RES_RANGE ci-dessous — on la déballe d'abord
        // pour valider l'IPv4 réellement contactée (sinon ::ffff:127.0.0.1 passerait le filtre).
        if (strlen($binary) === 16 && substr($binary, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            $ip = inet_ntop(substr($binary, 12));
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
