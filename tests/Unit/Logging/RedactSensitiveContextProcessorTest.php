<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\AddRedaction;
use App\Logging\RedactSensitiveContextProcessor;
use DateTimeImmutable;
use Illuminate\Log\Logger as LaravelLogger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

test('it redacts sensitive keys and preserves non-sensitive keys in context and extra', function () {
    $processor = new RedactSensitiveContextProcessor();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'testing',
        level: Level::Info,
        message: 'User authentication processed',
        context: [
            'user_id' => 42,
            'email' => 'user@example.com',
            'password' => 'super-secret-password',
            'api_key' => 'live_sk_1234567890',
            'profile' => [
                'name' => 'Jane Doe',
                'secret_answer' => 'fluffy',
                'metadata' => [
                    'session_token' => 'tok_abc123xyz',
                    'request_signature' => 'sig_987654321',
                    'hmac' => 'sha256_hash_here',
                    'card_number' => '4111222233334444',
                    'primary_account_pan' => '9876543210123456',
                    'passwd' => 'legacy_pwd',
                    'authorization' => 'Bearer eyJhbGciOi...',
                    'normal_metric' => 100,
                ],
            ],
        ],
        extra: [
            'ip' => '127.0.0.1',
            'access_token' => 'bearer-token-in-extra',
            'safe_extra' => 'not-sensitive',
        ],
    );

    $processed = $processor($record);

    expect($processed->context['user_id'])->toBe(42)
        ->and($processed->context['email'])->toBe('user@example.com')
        ->and($processed->context['password'])->toBe('[REDACTED]')
        ->and($processed->context['api_key'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['name'])->toBe('Jane Doe')
        ->and($processed->context['profile']['secret_answer'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['session_token'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['request_signature'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['hmac'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['card_number'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['primary_account_pan'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['passwd'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['authorization'])->toBe('[REDACTED]')
        ->and($processed->context['profile']['metadata']['normal_metric'])->toBe(100)
        ->and($processed->extra['ip'])->toBe('127.0.0.1')
        ->and($processed->extra['access_token'])->toBe('[REDACTED]')
        ->and($processed->extra['safe_extra'])->toBe('not-sensitive');
});

test('it keeps legitimate keys that contain substrings of sensitive words', function () {
    $processor = new RedactSensitiveContextProcessor();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'testing',
        level: Level::Info,
        message: 'Non-sensitive keys check',
        context: [
            'company_name' => 'Acme Inc',
            'expanded' => true,
            'tokens_count' => 12,
            'cardinality' => 500,
            'span' => 'full-width',
        ],
    );

    $processed = $processor($record);

    expect($processed->context['company_name'])->toBe('Acme Inc')
        ->and($processed->context['expanded'])->toBeTrue()
        ->and($processed->context['tokens_count'])->toBe(12)
        ->and($processed->context['cardinality'])->toBe(500)
        ->and($processed->context['span'])->toBe('full-width');
});

test('it redacts sensitive whole segments and segment pairs', function () {
    $processor = new RedactSensitiveContextProcessor();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'testing',
        level: Level::Info,
        message: 'Segment redaction verification',
        context: [
            'access_token' => 'jwt.token.value',
            'X-Api-Key' => 'key-secret-123',
            'cardNumber' => '1234-5678-9012-3456',
            'card_pan' => '9999888877776666',
            'Authorization' => 'Bearer token123',
            'hmac' => 'generated_hmac_hash',
            'cvv' => '123',
            'nested' => [
                'password' => 'plaintext_password',
            ],
        ],
    );

    $processed = $processor($record);

    expect($processed->context['access_token'])->toBe('[REDACTED]')
        ->and($processed->context['X-Api-Key'])->toBe('[REDACTED]')
        ->and($processed->context['cardNumber'])->toBe('[REDACTED]')
        ->and($processed->context['card_pan'])->toBe('[REDACTED]')
        ->and($processed->context['Authorization'])->toBe('[REDACTED]')
        ->and($processed->context['hmac'])->toBe('[REDACTED]')
        ->and($processed->context['cvv'])->toBe('[REDACTED]')
        ->and($processed->context['nested']['password'])->toBe('[REDACTED]');
});

test('it handles case-insensitivity and hyphenated keys', function (string $key) {
    $processor = new RedactSensitiveContextProcessor();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'testing',
        level: Level::Info,
        message: 'Testing key variance',
        context: [
            $key => 'sensitive-value',
            'non_sensitive' => 'safe-value',
        ],
    );

    $processed = $processor($record);

    expect($processed->context[$key])->toBe('[REDACTED]')
        ->and($processed->context['non_sensitive'])->toBe('safe-value');
})->with([
    'password' => 'PassWord',
    'passwd' => 'PASSWD',
    'token' => 'Bearer_Token',
    'secret' => 'Client_SECRET',
    'hmac' => 'request_HMAC',
    'signature' => 'X_Signature',
    'api_key' => 'api_key',
    'api-key' => 'api-key',
    'apikey' => 'ApiKey',
    'authorization' => 'AUTHORIZATION',
    'pan' => 'customer_pan',
    'card' => 'credit_card',
    'cvv' => 'cvv',
]);

test('it redacts when tapped into a monolog logger pipeline', function () {
    $processor = new RedactSensitiveContextProcessor();
    $tap = new AddRedaction($processor);

    $monolog = new MonologLogger('test_channel');
    $testHandler = new TestHandler();
    $monolog->pushHandler($testHandler);

    $logger = new LaravelLogger($monolog);
    $tap($logger);

    $logger->info('Payment payload', [
        'customer' => 'John',
        'credit_card' => '4000-0000-0000-0000',
        'nested' => [
            'api_key' => 'secret_live_key',
            'status' => 'success',
        ],
    ]);

    expect($testHandler->hasInfoRecords())->toBeTrue();

    $records = $testHandler->getRecords();
    expect($records)->toHaveCount(1);

    $record = $records[0];
    expect($record->context['customer'])->toBe('John')
        ->and($record->context['credit_card'])->toBe('[REDACTED]')
        ->and($record->context['nested']['api_key'])->toBe('[REDACTED]')
        ->and($record->context['nested']['status'])->toBe('success');
});
