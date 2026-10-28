<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactSensitiveContextProcessor implements ProcessorInterface
{
    private const array SENSITIVE_SEGMENTS = [
        'password',
        'passwd',
        'token',
        'secret',
        'hmac',
        'signature',
        'apikey',
        'authorization',
        'pan',
        'card',
        'cvv',
    ];

    private const string REDACTED_VALUE = '[REDACTED]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED_VALUE;
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactArray($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Determine if a context or extra key matches any sensitive segment or segment pair.
     */
    public function isSensitiveKey(string $key): bool
    {
        $segments = $this->extractKeySegments($key);

        foreach ($segments as $segment) {
            if (in_array($segment, self::SENSITIVE_SEGMENTS, true)) {
                return true;
            }
        }

        $segmentCount = count($segments);
        for ($i = 0; $i < $segmentCount - 1; $i++) {
            $pair = $segments[$i].$segments[$i + 1];
            if (in_array($pair, self::SENSITIVE_SEGMENTS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split key on '_', '-', '.', and camelCase boundaries into lowercased segments.
     *
     * @return list<string>
     */
    private function extractKeySegments(string $key): array
    {
        $splitCamel = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key);
        $splitUpper = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', (string) $splitCamel);
        $segments = preg_split('/[_\.\-\s]+/', (string) $splitUpper, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($segments)) {
            return [];
        }

        return array_values(array_map('strtolower', $segments));
    }
}
