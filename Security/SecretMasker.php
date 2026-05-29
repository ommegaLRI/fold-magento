<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Security;

/**
 * Masks values that are likely to contain credentials, tokens, secrets, or private customer/system data.
 */
final class SecretMasker
{
    private const MASK = '[masked]';

    /** @var string[] */
    private array $sensitivePathFragments = [
        'password',
        'passwd',
        'secret',
        'private_key',
        'privatekey',
        'api_key',
        'apikey',
        'access_key',
        'accesskey',
        'token',
        'client_secret',
        'auth',
        'oauth',
        'bearer',
        'signature',
        'salt',
        'crypt/key',
        'payment/',
        'paypal/',
        'braintree/',
        'stripe/',
        'adyen/',
        'klarna/',
        'authorize_net/',
        'cybersource/',
        'worldpay/',
        'amazon_payment/',
        'shipping/origin',
        'smtp/',
        'sftp/',
        'ftp/',
    ];

    /** @var string[] */
    private array $sensitiveExactPaths = [
        'web/cookie/cookie_domain',
        'web/cookie/cookie_path',
        'admin/security/session_cookie_lifetime',
    ];

    /**
     * @param string[] $extraSensitiveFragments
     * @param string[] $extraSensitiveExactPaths
     */
    public function __construct(array $extraSensitiveFragments = [], array $extraSensitiveExactPaths = [])
    {
        $this->sensitivePathFragments = array_values(array_unique(array_merge(
            $this->sensitivePathFragments,
            array_map('strval', $extraSensitiveFragments)
        )));

        $this->sensitiveExactPaths = array_values(array_unique(array_merge(
            $this->sensitiveExactPaths,
            array_map('strval', $extraSensitiveExactPaths)
        )));
    }

    public function mask(string $path, mixed $value): mixed
    {
        if (!$this->isSensitivePath($path)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $value;
        }

        return self::MASK;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function maskRecord(array $record, string $pathKey = 'path', string $valueKey = 'value'): array
    {
        $path = isset($record[$pathKey]) ? (string) $record[$pathKey] : '';
        if ($path === '' || !array_key_exists($valueKey, $record)) {
            return $record;
        }

        $record[$valueKey] = $this->mask($path, $record[$valueKey]);
        $record['is_sensitive'] = $this->isSensitivePath($path);

        return $record;
    }

    public function isSensitivePath(string $path): bool
    {
        $normalized = $this->normalize($path);

        foreach ($this->sensitiveExactPaths as $exactPath) {
            if ($normalized === $this->normalize($exactPath)) {
                return true;
            }
        }

        foreach ($this->sensitivePathFragments as $fragment) {
            $fragment = $this->normalize($fragment);
            if ($fragment !== '' && str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public function summarizeValue(mixed $value): array
    {
        if ($value === null) {
            return [
                'type' => 'null',
                'length' => 0,
                'is_empty' => true,
            ];
        }

        if (is_bool($value)) {
            return [
                'type' => 'boolean',
                'length' => null,
                'is_empty' => false,
            ];
        }

        if (is_int($value) || is_float($value)) {
            return [
                'type' => is_int($value) ? 'integer' : 'float',
                'length' => strlen((string) $value),
                'is_empty' => false,
            ];
        }

        if (is_array($value)) {
            return [
                'type' => 'array',
                'length' => count($value),
                'is_empty' => count($value) === 0,
            ];
        }

        $string = (string) $value;

        return [
            'type' => 'string',
            'length' => strlen($string),
            'is_empty' => trim($string) === '',
        ];
    }

    public function getMask(): string
    {
        return self::MASK;
    }

    private function normalize(string $path): string
    {
        return strtolower(str_replace(['-', '.', '\\'], ['_', '_', '/'], trim($path)));
    }
}
