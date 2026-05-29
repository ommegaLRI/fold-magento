<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Output;

use CommerceXray\MagentoXray\Scanner\ScanContext;
use CommerceXray\MagentoXray\Scanner\ScanResult;
use CommerceXray\MagentoXray\StoreGraph\JsonlWriter;
use CommerceXray\MagentoXray\StoreGraph\StoreGraphWriter;
use RuntimeException;

final class BundleWriter
{
    private ManifestWriter $manifestWriter;
    private StoreGraphWriter $storeGraphWriter;
    private JsonlWriter $jsonlWriter;

    public function __construct(
        ManifestWriter $manifestWriter,
        StoreGraphWriter $storeGraphWriter,
        JsonlWriter $jsonlWriter
    ) {
        $this->manifestWriter = $manifestWriter;
        $this->storeGraphWriter = $storeGraphWriter;
        $this->jsonlWriter = $jsonlWriter;
    }

    public function write(ScanContext $context, ScanResult $result): string
    {
        $scanDir = $context->getScanDir();
        $this->ensureDirectory($scanDir);

        $this->storeGraphWriter->write($scanDir, $result);

        $this->jsonlWriter->write(
            $scanDir . DIRECTORY_SEPARATOR . 'config.schema.jsonl',
            $result->getConfigSchema()
        );

        $this->jsonlWriter->write(
            $scanDir . DIRECTORY_SEPARATOR . 'config.state.jsonl',
            $result->getConfigState()
        );

        $this->jsonlWriter->write(
            $scanDir . DIRECTORY_SEPARATOR . 'orphans.jsonl',
            $result->getOrphans()
        );

        $this->writeJson(
            $scanDir . DIRECTORY_SEPARATOR . 'scan-errors.json',
            [
                'count' => count($result->getErrors()),
                'errors' => $result->getErrors(),
            ]
        );

        $this->writeFallbackSummary($scanDir, $context, $result);
        $this->manifestWriter->write($scanDir, $context, $result);

        return $scanDir;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create scan output directory: %s', $directory));
        }
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to encode JSON file %s: %s', $path, json_last_error_msg()));
        }

        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Unable to write JSON file: %s', $path));
        }
    }

    private function writeFallbackSummary(string $scanDir, ScanContext $context, ScanResult $result): void
    {
        $counts = $result->getCounts();
        $lines = [
            '# fold-magento Scan Summary',
            '',
            sprintf('- Scan ID: `%s`', $context->getScanId()),
            sprintf('- Profile: `%s`', $context->getProfile()),
            sprintf('- Started at: `%s`', $context->getStartedAtIso()),
            '',
            '## Record counts',
            '',
        ];

        foreach ($counts as $name => $count) {
            $lines[] = sprintf('- %s: %d', str_replace('_', ' ', $name), $count);
        }

        $lines[] = '';
        $lines[] = 'This is the Tier 2 fallback summary. A richer summary writer can replace this in Tier 4.';
        $lines[] = '';

        $path = $scanDir . DIRECTORY_SEPARATOR . 'summary.md';
        if (file_put_contents($path, implode(PHP_EOL, $lines)) === false) {
            throw new RuntimeException(sprintf('Unable to write summary file: %s', $path));
        }
    }
}
