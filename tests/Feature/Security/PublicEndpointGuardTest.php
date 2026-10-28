<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Rules\PublicHttpUrl;
use App\Support\Network\InvalidEndpointException;
use App\Support\Network\PublicEndpointGuard;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class PublicEndpointGuardTest extends TestCase
{
    private PublicEndpointGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        config(['network.allow_private_hosts' => false]);
        $this->guard = new PublicEndpointGuard();
    }

    public function test_it_rejects_loopback_ipv4(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->guard->guard('127.0.0.1');
    }

    public function test_it_rejects_private_range_ipv4(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->guard->guard('10.0.0.5');
    }

    public function test_it_rejects_link_local_metadata_ipv4(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->guard->guard('169.254.169.254');
    }

    public function test_it_rejects_ipv6_loopback(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->guard->guard('[::1]');
    }

    public function test_it_rejects_ipv4_mapped_ipv6_private(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->guard->guard('[::ffff:127.0.0.1]');
    }

    public function test_it_allows_ipv4_mapped_ipv6_public(): void
    {
        $this->guard->guard('[::ffff:8.8.8.8]');
        $this->assertTrue(true);
    }

    public function test_it_rejects_urls_targeting_loopback_ip(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->guard->guard('http://127.0.0.1:8080/api');
    }

    public function test_it_rejects_urls_with_userinfo(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->expectExceptionMessage('Userinfo is not allowed');
        $this->guard->guard('http://admin:secret@93.184.216.34/pos');
    }

    public function test_it_rejects_unsupported_schemes(): void
    {
        $this->expectException(InvalidEndpointException::class);
        $this->expectExceptionMessage('Unsupported scheme');
        $this->guard->guard('ftp://93.184.216.34/pos');
    }

    public function test_it_allows_public_ip_literals(): void
    {
        $this->guard->guard('8.8.8.8');
        $this->guard->guard('https://93.184.216.34/api/pos');
        $this->assertTrue(true);
    }

    public function test_it_rejects_domains_resolving_to_private_ips(): void
    {
        $guardWithCustomResolver = new PublicEndpointGuard(
            dnsResolver: fn (string $host) => ['127.0.0.1']
        );

        $this->expectException(InvalidEndpointException::class);
        $guardWithCustomResolver->guard('https://malicious-local.test/api');
    }

    public function test_it_allows_private_hosts_when_configured(): void
    {
        config(['network.allow_private_hosts' => true]);

        $this->guard->guard('http://127.0.0.1:8000/api');
        $this->guard->guard('10.0.0.5');
        $this->assertTrue(true);
    }

    public function test_public_http_url_validation_rule_validates_input(): void
    {
        $v1 = Validator::make(['link' => 'https://93.184.216.34/api'], [
            'link' => [new PublicHttpUrl()],
        ]);
        $this->assertTrue($v1->passes());

        $v2 = Validator::make(['link' => 'http://127.0.0.1:8000/api'], [
            'link' => [new PublicHttpUrl()],
        ]);
        $this->assertTrue($v2->fails());

        $v3 = Validator::make(['link' => 'not-a-url'], [
            'link' => [new PublicHttpUrl()],
        ]);
        $this->assertTrue($v3->fails());
    }
}
