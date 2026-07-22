<?php

namespace Tests\Unit\Services\DataIngestion\Security;

use App\Domain\DataIngestion\Exceptions\SsrfBlockedException;
use App\Services\DataIngestion\Security\SsrfGuard;
use Tests\TestCase;

class SsrfGuardTest extends TestCase
{
    public function test_allows_a_public_ip_literal(): void
    {
        $this->expectNotToPerformAssertions();

        (new SsrfGuard())->assertUrlIsSafe('https://1.1.1.1/data');
    }

    public function test_allows_a_public_hostname_resolved_via_dns(): void
    {
        $this->expectNotToPerformAssertions();

        (new SsrfGuard())->assertUrlIsSafe('https://example.com/data');
    }

    public function test_rejects_loopback_ip_literal(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('http://127.0.0.1/admin');
    }

    public function test_rejects_ipv6_loopback_literal(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('http://[::1]/admin');
    }

    public function test_rejects_private_rfc1918_ip_literal(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('http://10.0.0.5/internal');
    }

    public function test_rejects_link_local_metadata_ip(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('http://169.254.169.254/latest/meta-data');
    }

    public function test_rejects_ipv4_mapped_ipv6_loopback(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('http://[::ffff:127.0.0.1]/admin');
    }

    public function test_rejects_non_http_scheme(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('file:///etc/passwd');
    }

    public function test_rejects_unresolvable_hostname(): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard())->assertUrlIsSafe('https://this-host-does-not-exist.invalid/data');
    }
}
