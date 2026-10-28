<?php

declare(strict_types=1);

namespace App\Support\Network;

use Closure;

final class PublicEndpointGuard
{
    private ?Closure $dnsResolver;

    public function __construct(?callable $dnsResolver = null)
    {
        $this->dnsResolver = $dnsResolver !== null ? Closure::fromCallable($dnsResolver) : null;
    }

    /**
     * Check whether an IP address belongs to private, loopback, link-local, or reserved ranges.
     */
    public static function isPrivateOrReservedIp(string $ip): bool
    {
        $cleanIp = mb_trim($ip, '[]');

        if (! filter_var($cleanIp, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($cleanIp);
            if ($packed === false) {
                return true;
            }

            // IPv4-mapped IPv6 (::ffff:0:0/96)
            if (str_starts_with($packed, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff")) {
                $unpacked = unpack('C4', $packed, 12);
                if (is_array($unpacked) && count($unpacked) === 4) {
                    $mappedIpv4 = implode('.', $unpacked);

                    return self::isPrivateOrReservedIp($mappedIpv4);
                }

                return true;
            }

            // ::1 Loopback
            if ($packed === inet_pton('::1')) {
                return true;
            }

            // :: Unspecified
            if ($packed === inet_pton('::')) {
                return true;
            }

            // fc00::/7 Unique Local Address (ULA)
            if ((ord($packed[0]) & 0xFE) === 0xFC) {
                return true;
            }

            // fe80::/10 Link-Local Unicast
            if (ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0x80) {
                return true;
            }

            if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }

            return false;
        }

        if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($cleanIp);
            if ($long === false) {
                return true;
            }

            // 169.254.0.0/16 Link-Local
            if (($long & (int) 0xFFFF0000) === (int) ip2long('169.254.0.0')) {
                return true;
            }

            // 127.0.0.0/8 Loopback
            if (($long & (int) 0xFF000000) === (int) ip2long('127.0.0.0')) {
                return true;
            }

            // 0.0.0.0/8 Current network
            if (($long & (int) 0xFF000000) === 0) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Validate an endpoint URL or host and reject unsafe/internal targets.
     *
     * @throws InvalidEndpointException
     */
    public function guard(string $target): void
    {
        if (config('network.allow_private_hosts', false)) {
            return;
        }

        $trimmed = mb_trim($target);
        if ($trimmed === '') {
            throw new InvalidEndpointException('Endpoint cannot be empty.');
        }

        if (! str_contains($trimmed, '://')) {
            $this->guardHostOrIp($trimmed);

            return;
        }

        $parsed = parse_url($trimmed);
        if (! is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new InvalidEndpointException('Invalid endpoint URL.');
        }

        $scheme = mb_strtolower((string) $parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidEndpointException("Unsupported scheme [{$scheme}]. Only http and https are allowed.");
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new InvalidEndpointException('Userinfo is not allowed in endpoint URL.');
        }

        $this->guardHostOrIp((string) $parsed['host']);
    }

    private function guardHostOrIp(string $host): void
    {
        $cleanHost = mb_trim($host, '[]');

        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            if (self::isPrivateOrReservedIp($cleanHost)) {
                throw new InvalidEndpointException("Host IP [{$cleanHost}] is in a private, loopback, or reserved range.");
            }

            return;
        }

        if (mb_strtolower($cleanHost) === 'localhost') {
            throw new InvalidEndpointException('Host [localhost] is not allowed.');
        }

        $resolvedIps = $this->resolveHost($cleanHost);
        foreach ($resolvedIps as $resolvedIp) {
            if (self::isPrivateOrReservedIp($resolvedIp)) {
                throw new InvalidEndpointException("Resolved address [{$resolvedIp}] for host [{$cleanHost}] is in a private, loopback, or reserved range.");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        if ($this->dnsResolver !== null) {
            $resolved = ($this->dnsResolver)($host);

            return is_array($resolved) ? array_values($resolved) : [];
        }

        $ips = [];

        $ipv4s = @gethostbynamel($host);
        if (is_array($ipv4s)) {
            foreach ($ipv4s as $ip) {
                if (is_string($ip)) {
                    $ips[] = $ip;
                }
            }
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return $ips;
    }
}
