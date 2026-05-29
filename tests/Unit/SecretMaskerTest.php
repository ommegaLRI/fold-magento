<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Tests\Unit;

use CommerceXray\MagentoXray\Security\SecretMasker;
use PHPUnit\Framework\TestCase;

final class SecretMaskerTest extends TestCase
{
    public function testMasksPasswordConfigPath(): void
    {
        $masker = new SecretMasker();

        self::assertSame('[masked]', $masker->mask('payment/example/password', 'super-secret'));
        self::assertTrue($masker->isSensitivePath('payment/example/password'));
    }

    public function testMasksCryptKeyPath(): void
    {
        $masker = new SecretMasker();

        self::assertSame('[masked]', $masker->mask('crypt/key', 'abc123'));
        self::assertTrue($masker->isSensitivePath('crypt/key'));
    }

    public function testLeavesNonSensitiveValuesUnchanged(): void
    {
        $masker = new SecretMasker();

        self::assertSame('My Store', $masker->mask('general/store_information/name', 'My Store'));
        self::assertFalse($masker->isSensitivePath('general/store_information/name'));
    }

    public function testLeavesEmptySensitiveValuesEmpty(): void
    {
        $masker = new SecretMasker();

        self::assertSame('', $masker->mask('payment/example/api_key', ''));
        self::assertNull($masker->mask('payment/example/api_key', null));
    }

    public function testMaskRecordAddsSensitivityFlag(): void
    {
        $masker = new SecretMasker();

        $record = $masker->maskRecord([
            'path' => 'smtp/provider/password',
            'value' => 'secret-value',
        ]);

        self::assertSame('[masked]', $record['value']);
        self::assertTrue($record['is_sensitive']);
    }

    public function testMaskRecordLeavesRecordsWithoutPathAlone(): void
    {
        $masker = new SecretMasker();
        $record = ['value' => 'secret-value'];

        self::assertSame($record, $masker->maskRecord($record));
    }

    public function testCustomSensitiveFragmentCanBeInjected(): void
    {
        $masker = new SecretMasker(['license_key']);

        self::assertSame('[masked]', $masker->mask('vendor/module/license_key', 'abc123'));
        self::assertTrue($masker->isSensitivePath('vendor/module/license_key'));
    }

    public function testSummarizeValueForString(): void
    {
        $masker = new SecretMasker();

        self::assertSame([
            'type' => 'string',
            'length' => 5,
            'is_empty' => false,
        ], $masker->summarizeValue('hello'));
    }

    public function testSummarizeValueForNull(): void
    {
        $masker = new SecretMasker();

        self::assertSame([
            'type' => 'null',
            'length' => 0,
            'is_empty' => true,
        ], $masker->summarizeValue(null));
    }

    public function testSummarizeValueForArray(): void
    {
        $masker = new SecretMasker();

        self::assertSame([
            'type' => 'array',
            'length' => 2,
            'is_empty' => false,
        ], $masker->summarizeValue(['a', 'b']));
    }
}
