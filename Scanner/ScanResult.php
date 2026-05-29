<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\StoreGraph\Entity;
use CommerceXray\MagentoXray\StoreGraph\Relationship;
use Throwable;

/**
 * Mutable in-memory scan bundle assembled by collectors before it is written.
 */
final class ScanResult
{
    /** @var array<int, array<string, mixed>> */
    private array $entities = [];
    /** @var array<int, array<string, mixed>> */
    private array $relationships = [];
    /** @var array<int, array<string, mixed>> */
    private array $configSchema = [];
    /** @var array<int, array<string, mixed>> */
    private array $configState = [];
    /** @var array<int, array<string, mixed>> */
    private array $orphans = [];
    /** @var array<int, array<string, mixed>> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $summary = [];
    /** @var array<string, mixed> */
    private array $metadata = [];

    /** @param array<string, mixed>|Entity $entity */
    public function addEntity(array|Entity $entity): void
    {
        $this->entities[] = $entity instanceof Entity ? $entity->toArray() : $entity;
    }

    /** @param array<string, mixed>|Relationship $relationship */
    public function addRelationship(array|Relationship $relationship): void
    {
        $this->relationships[] = $relationship instanceof Relationship ? $relationship->toArray() : $relationship;
    }

    /** @param array<string, mixed> $record */
    public function addConfigSchema(array $record): void
    {
        $this->configSchema[] = $record;
    }

    /** @param array<string, mixed> $record */
    public function addConfigState(array $record): void
    {
        $this->configState[] = $record;
    }

    /** @param array<string, mixed> $record */
    public function addOrphan(array $record): void
    {
        $this->orphans[] = $record;
    }

    /**
     * @param array<string, mixed>|string $error
     */
    public function addError(array|string $error, ?Throwable $throwable = null): void
    {
        if (is_string($error)) {
            $error = ['message' => $error];
        }

        if ($throwable !== null) {
            $error = array_replace([
                'type' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ], $error);
        }

        $error['recorded_at'] = gmdate(DATE_ATOM);
        $this->errors[] = $error;
    }

    public function setSummaryValue(string $key, mixed $value): void
    {
        $this->summary[$key] = $value;
    }

    public function incrementSummaryValue(string $key, int $amount = 1): void
    {
        $current = $this->summary[$key] ?? 0;
        $this->summary[$key] = is_numeric($current) ? (int) $current + $amount : $amount;
    }

    public function setMetadataValue(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /** @return array<int, array<string, mixed>> */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRelationships(): array
    {
        return $this->relationships;
    }

    /** @return array<int, array<string, mixed>> */
    public function getConfigSchema(): array
    {
        return $this->configSchema;
    }

    /** @return array<int, array<string, mixed>> */
    public function getConfigState(): array
    {
        return $this->configState;
    }

    /** @return array<int, array<string, mixed>> */
    public function getOrphans(): array
    {
        return $this->orphans;
    }

    /** @return array<int, array<string, mixed>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @return array<string, int> */
    public function getCounts(): array
    {
        return [
            'entities' => count($this->entities),
            'relationships' => count($this->relationships),
            'config_schema' => count($this->configSchema),
            'config_state' => count($this->configState),
            'orphans' => count($this->orphans),
            'errors' => count($this->errors),
        ];
    }
}
