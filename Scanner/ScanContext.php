<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Immutable value object describing a single X-Ray scan execution.
 */
final class ScanContext
{
    public const DEFAULT_PROFILE = 'minimal';

    private string $profile;
    private string $outputRoot;
    private string $scanId;
    private DateTimeImmutable $startedAt;
    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $profile = self::DEFAULT_PROFILE,
        string $outputRoot = 'var/xray/scans',
        ?string $scanId = null,
        ?DateTimeImmutable $startedAt = null,
        array $metadata = []
    ) {
        $profile = trim($profile);
        if ($profile === '') {
            throw new InvalidArgumentException('Scan profile cannot be empty.');
        }

        $outputRoot = rtrim(trim($outputRoot), DIRECTORY_SEPARATOR);
        if ($outputRoot === '') {
            throw new InvalidArgumentException('Output root cannot be empty.');
        }

        $this->profile = $profile;
        $this->outputRoot = $outputRoot;
        $this->startedAt = $startedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->scanId = $scanId !== null && trim($scanId) !== ''
            ? $this->sanitizeScanId($scanId)
            : $this->startedAt->format('Ymd\THis\Z');
        $this->metadata = $metadata;
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function getOutputRoot(): string
    {
        return $this->outputRoot;
    }

    public function getScanId(): string
    {
        return $this->scanId;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getStartedAtIso(): string
    {
        return $this->startedAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }

    public function getScanDir(): string
    {
        return $this->outputRoot . DIRECTORY_SEPARATOR . $this->scanId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->metadata) ? $this->metadata[$key] : $default;
    }

    public function withMetadataValue(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;

        return $clone;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = array_replace($clone->metadata, $metadata);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile' => $this->profile,
            'output_root' => $this->outputRoot,
            'scan_id' => $this->scanId,
            'scan_dir' => $this->getScanDir(),
            'started_at' => $this->getStartedAtIso(),
            'metadata' => $this->metadata,
        ];
    }

    private function sanitizeScanId(string $scanId): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '-', trim($scanId));
        $sanitized = trim((string) $sanitized, '.-_');

        if ($sanitized === '') {
            throw new InvalidArgumentException('Scan ID must contain at least one safe character.');
        }

        return $sanitized;
    }
}
