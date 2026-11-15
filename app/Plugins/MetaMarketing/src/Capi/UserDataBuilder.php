<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Capi;

use App\Plugins\MetaMarketing\src\Models\MetaOrderTracking;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Http\Request;

final class UserDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forOrder(Order $order, ?MetaOrderTracking $tracking = null): array
    {
        $userData = [];

        $email = $this->normalizeEmail($order->getCustomerEmail() ?? $order->customer_email);
        if ($email !== null) {
            $userData['em'] = [$this->hash($email)];
        }

        $phone = $this->normalizePhone($order->getCustomerPhone() ?? $order->customer_phone);
        if ($phone !== null) {
            $userData['ph'] = [$this->hash($phone)];
        }

        $name = $order->getCustomerName();
        [$fn, $ln] = $this->splitName($name);
        if ($fn !== null) {
            $userData['fn'] = [$this->hash($fn)];
        }
        if ($ln !== null) {
            $userData['ln'] = [$this->hash($ln)];
        }

        $city = $this->extractCityFromOrder($order);
        $normalizedCity = $this->normalizeCity($city);
        if ($normalizedCity !== null) {
            $userData['ct'] = [$this->hash($normalizedCity)];
        }

        $userData['country'] = [$this->hash('eg')];

        if ($order->user_id !== null) {
            $userData['external_id'] = [$this->hash('u:'.$order->user_id)];
        } elseif ($order->guest_user_id !== null) {
            $userData['external_id'] = [$this->hash('g:'.$order->guest_user_id)];
        }

        if ($tracking !== null) {
            if (filled($tracking->client_ip)) {
                $userData['client_ip_address'] = $tracking->client_ip;
            }
            if (filled($tracking->user_agent)) {
                $userData['client_user_agent'] = $tracking->user_agent;
            }
            if (filled($tracking->fbp)) {
                $userData['fbp'] = $tracking->fbp;
            }
            if (filled($tracking->fbc)) {
                $userData['fbc'] = $tracking->fbc;
            }
        }

        return $userData;
    }

    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        $userData = [];

        $user = $request->user();
        if ($user instanceof \App\Models\User) {
            $email = $this->normalizeEmail($user->customerEmail());
            if ($email !== null) {
                $userData['em'] = [$this->hash($email)];
            }

            $phone = $this->normalizePhone($user->customerPhone());
            if ($phone !== null) {
                $userData['ph'] = [$this->hash($phone)];
            }

            [$fn, $ln] = $this->splitName($user->customerName());
            if ($fn !== null) {
                $userData['fn'] = [$this->hash($fn)];
            }
            if ($ln !== null) {
                $userData['ln'] = [$this->hash($ln)];
            }

            $userData['external_id'] = [$this->hash('u:'.$user->getAuthIdentifier())];
        }

        $userData['country'] = [$this->hash('eg')];

        $clientIp = $request->ip();
        if (filled($clientIp)) {
            $userData['client_ip_address'] = $clientIp;
        }

        $userAgent = $request->userAgent();
        if (filled($userAgent)) {
            $userData['client_user_agent'] = $userAgent;
        }

        $fbp = $request->cookie('_fbp');
        if (filled($fbp)) {
            $userData['fbp'] = (string) $fbp;
        }

        $fbc = $request->cookie('_fbc');
        if (filled($fbc)) {
            $userData['fbc'] = (string) $fbc;
        }

        return $userData;
    }

    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0020')) {
            $digits = mb_substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '20'.mb_substr($digits, 1);
        } elseif (! str_starts_with($digits, '20') && mb_strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '20'.$digits;
        }

        return $digits;
    }

    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $trimmed = mb_strtolower(mb_trim($email));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function splitName(?string $name): array
    {
        if ($name === null) {
            return [null, null];
        }

        $trimmed = mb_trim($name);
        if ($trimmed === '') {
            return [null, null];
        }

        $parts = explode(' ', $trimmed, 2);
        $fn = mb_trim($parts[0]);
        $ln = isset($parts[1]) ? mb_trim($parts[1]) : null;

        $first = $fn !== '' ? mb_strtolower($fn, 'UTF-8') : null;
        $last = ($ln !== null && mb_trim($ln) !== '') ? mb_strtolower(mb_trim($ln), 'UTF-8') : null;

        return [$first, $last];
    }

    public function normalizeCity(?string $city): ?string
    {
        if ($city === null) {
            return null;
        }

        $cleaned = preg_replace('/\s+/', '', mb_strtolower(mb_trim($city), 'UTF-8'));

        return ($cleaned !== null && $cleaned !== '') ? $cleaned : null;
    }

    public function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    private function extractCityFromOrder(Order $order): ?string
    {
        if (is_array($order->delivery_address)) {
            $val = $order->delivery_address['city']
                ?? $order->delivery_address['area_name']
                ?? $order->delivery_address['governorate']
                ?? null;

            if (is_string($val) && mb_trim($val) !== '') {
                return $val;
            }
        }

        if ($order->relationLoaded('address') && $order->address !== null) {
            $val = $order->address->city ?? $order->address->area->name ?? null;
            if (is_string($val) && mb_trim($val) !== '') {
                return $val;
            }
        }

        return null;
    }
}
