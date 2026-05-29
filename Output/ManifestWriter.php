<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Output;

use CommerceXray\MagentoXray\Scanner\ScanContext;
use CommerceXray\MagentoXray\Scanner\ScanResult;
use RuntimeException;

final class ManifestWriter
{
    public const SCHEMA_VERSION = '0.1.0';
    public const SCANNER_NAME = 'magento-xray';

    public function write(string $scanDir, ScanContext $context, ScanResult $result): void
    {
        $manifest = [
            'schema_version' => self::SCHEMA_VERSION,
            'scanner' => self::SCANNER_NAME,
            'profile' => $context->getProfile(),
            'scan_id' => $context->getScanId(),
            'generated_at' => gmdate(DATE_ATOM),
            'started_at' => $context->getStartedAtIso(),
            'counts' => $result->getCounts(),
            'files' => [
                'manifest' => 'manifest.json',
                'entities' => 'storegraph.entities.jsonl',
                'relationships' => 'storegraph.relationships.jsonl',
                'config_schema' => 'config.schema.jsonl',
                'config_state' => 'config.state.jsonl',
                'orphans' => 'orphans.jsonl',
                'scan_errors' => 'scan-errors.json',
                'summary' => 'summary.md',
            ],
            'context' => $context->toArray(),
            'summary' => $result->getSummary(),
            'metadata' => $result->getMetadata(),
        ];

        $this->writeJson($scanDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to encode manifest JSON: %s', json_last_error_msg()));
        }

        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Unable to write manifest file: %s', $path));
        }
    }
}
