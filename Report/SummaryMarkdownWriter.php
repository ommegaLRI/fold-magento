<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Report;

use CommerceXray\MagentoXray\Scanner\ScanContext;
use CommerceXray\MagentoXray\Scanner\ScanResult;
use RuntimeException;

/**
 * Writes the human-readable scan summary.
 *
 * The storegraph JSONL files remain the canonical output. This summary exists
 * to make a scan quickly understandable without extra tooling.
 */
final class SummaryMarkdownWriter
{
    public function write(string $scanDir, ScanContext $context, ScanResult $result): void
    {
        if (!is_dir($scanDir)) {
            throw new RuntimeException(sprintf('Summary output directory does not exist: %s', $scanDir));
        }

        $path = $scanDir . DIRECTORY_SEPARATOR . 'summary.md';
        $markdown = $this->render($context, $result);

        if (file_put_contents($path, $markdown) === false) {
            throw new RuntimeException(sprintf('Unable to write summary file: %s', $path));
        }
    }

    public function render(ScanContext $context, ScanResult $result): string
    {
        $summary = $result->getSummary();
        $counts = $result->getCounts();
        $lines = [];

        $lines[] = '# fold-magento Scan Summary';
        $lines[] = '';
        $lines[] = '> This report was generated locally from a read-only fold-magento scan. The machine-readable storegraph files are the source of truth.';
        $lines[] = '';

        $lines[] = '## Scan';
        $lines[] = '';
        $lines[] = sprintf('- Scan ID: `%s`', $context->getScanId());
        $lines[] = sprintf('- Profile: `%s`', $context->getProfile());
        $lines[] = sprintf('- Started at: `%s`', $context->getStartedAtIso());
        $lines[] = sprintf('- Output directory: `%s`', $context->getScanDir());
        $lines[] = '';

        $lines[] = '## Platform';
        $lines[] = '';
        $lines[] = sprintf('- Magento version: `%s`', $this->formatValue($summary['magento_version'] ?? 'unknown'));
        $lines[] = sprintf('- Magento edition: `%s`', $this->formatValue($summary['magento_edition'] ?? 'unknown'));
        $lines[] = sprintf('- PHP version: `%s`', $this->formatValue($summary['php_version'] ?? PHP_VERSION));
        $lines[] = sprintf('- Runtime mode: `%s`', $this->formatValue($summary['runtime_mode'] ?? 'unknown'));
        $lines[] = '';

        $lines[] = '## Store structure';
        $lines[] = '';
        $lines[] = sprintf('- Websites: %d', $this->intValue($summary['website_count'] ?? 0));
        $lines[] = sprintf('- Store groups: %d', $this->intValue($summary['store_group_count'] ?? 0));
        $lines[] = sprintf('- Store views: %d', $this->intValue($summary['store_view_count'] ?? 0));
        $lines[] = sprintf('- Active store views: %d', $this->intValue($summary['active_store_view_count'] ?? 0));
        $lines[] = '';

        $lines[] = '## Modules and packages';
        $lines[] = '';
        $lines[] = sprintf('- Magento modules: %d', $this->intValue($summary['module_count'] ?? 0));
        $lines[] = sprintf('- Enabled modules: %d', $this->intValue($summary['enabled_module_count'] ?? 0));
        $lines[] = sprintf('- Disabled modules: %d', $this->intValue($summary['disabled_module_count'] ?? 0));
        $lines[] = sprintf('- Composer packages: %d', $this->intValue($summary['composer_package_count'] ?? 0));
        $lines[] = sprintf('- Magento-related Composer packages: %d', $this->intValue($summary['composer_magento_package_count'] ?? 0));
        $lines[] = sprintf('- Likely third-party Composer packages: %d', $this->intValue($summary['composer_third_party_package_count'] ?? 0));
        $lines[] = '';

        $lines[] = '## Configuration';
        $lines[] = '';
        $lines[] = sprintf('- Known config schema fields: %d', $this->intValue($summary['config_schema_count'] ?? $counts['config_schema'] ?? 0));
        $lines[] = sprintf('- Config sections discovered: %d', $this->intValue($summary['config_section_count'] ?? 0));
        $lines[] = sprintf('- Config values found: %d', $this->intValue($summary['config_state_count'] ?? $counts['config_state'] ?? 0));
        $lines[] = sprintf('- Scope-level overrides: %d', $this->intValue($summary['config_override_count'] ?? 0));
        $lines[] = sprintf('- Sensitive values masked: %d', $this->intValue($summary['config_sensitive_value_count'] ?? 0));
        $lines[] = sprintf('- Likely orphan config paths: %d', $this->intValue($summary['orphan_config_path_count'] ?? $counts['orphans'] ?? 0));
        $lines[] = '';

        $scopeCounts = $summary['config_state_scope_counts'] ?? null;
        if (is_array($scopeCounts) && $scopeCounts !== []) {
            $lines[] = '### Config values by scope';
            $lines[] = '';
            foreach ($scopeCounts as $scope => $count) {
                $lines[] = sprintf('- `%s`: %d', (string) $scope, $this->intValue($count));
            }
            $lines[] = '';
        }

        $lines[] = '## Storegraph files';
        $lines[] = '';
        $lines[] = sprintf('- `storegraph.entities.jsonl`: %d records', $this->intValue($counts['entities'] ?? 0));
        $lines[] = sprintf('- `storegraph.relationships.jsonl`: %d records', $this->intValue($counts['relationships'] ?? 0));
        $lines[] = sprintf('- `config.schema.jsonl`: %d records', $this->intValue($counts['config_schema'] ?? 0));
        $lines[] = sprintf('- `config.state.jsonl`: %d records', $this->intValue($counts['config_state'] ?? 0));
        $lines[] = sprintf('- `orphans.jsonl`: %d records', $this->intValue($counts['orphans'] ?? 0));
        $lines[] = sprintf('- `scan-errors.json`: %d errors', $this->intValue($counts['errors'] ?? 0));
        $lines[] = '';

        $errors = $result->getErrors();
        if ($errors !== []) {
            $lines[] = '## Scan errors';
            $lines[] = '';
            $lines[] = sprintf('The scan completed with %d recorded error(s). Review `scan-errors.json` for complete details.', count($errors));
            $lines[] = '';

            foreach (array_slice($errors, 0, 10) as $error) {
                $collector = isset($error['collector']) ? (string) $error['collector'] : 'unknown';
                $message = isset($error['message']) ? (string) $error['message'] : 'No message provided.';
                $lines[] = sprintf('- `%s`: %s', $collector, $this->escapeInlineMarkdown($message));
            }

            if (count($errors) > 10) {
                $lines[] = sprintf('- …and %d more.', count($errors) - 10);
            }

            $lines[] = '';
        }

        $lines[] = '## Privacy notes';
        $lines[] = '';
        $lines[] = '- fold-magento is designed as a local, read-only scanner.';
        $lines[] = '- The MVP does not upload scan data.';
        $lines[] = '- Sensitive configuration values are masked before they are written to the bundle.';
        $lines[] = '- Review `docs/privacy.md` before sharing scan output with another party.';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'unknown';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'unknown';
    }

    private function escapeInlineMarkdown(string $value): string
    {
        return str_replace(['`', "\n", "\r"], ["'", ' ', ' '], $value);
    }
}
