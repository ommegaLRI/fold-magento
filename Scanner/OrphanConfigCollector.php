<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

final class OrphanConfigCollector implements CollectorInterface
{
    public function collect(ScanContext $context, ScanResult $result): void
    {
        unset($context);

        $schemaPaths = [];
        foreach ($result->getConfigSchema() as $schemaRecord) {
            $path = isset($schemaRecord['path']) ? trim((string) $schemaRecord['path']) : '';
            if ($path !== '') {
                $schemaPaths[$path] = true;
            }
        }

        $stateByPath = [];
        foreach ($result->getConfigState() as $stateRecord) {
            $path = isset($stateRecord['path']) ? trim((string) $stateRecord['path']) : '';
            if ($path === '') {
                continue;
            }

            if (!isset($stateByPath[$path])) {
                $stateByPath[$path] = [
                    'path' => $path,
                    'occurrence_count' => 0,
                    'scopes' => [],
                    'scope_ids' => [],
                    'scope_codes' => [],
                    'has_sensitive_value' => false,
                    'has_override' => false,
                    'sample_value' => null,
                    'sample_value_summary' => null,
                ];
            }

            $stateByPath[$path]['occurrence_count']++;

            $scope = isset($stateRecord['scope']) ? (string) $stateRecord['scope'] : null;
            if ($scope !== null && $scope !== '') {
                $stateByPath[$path]['scopes'][$scope] = true;
            }

            if (isset($stateRecord['scope_id'])) {
                $stateByPath[$path]['scope_ids'][(string) $stateRecord['scope_id']] = true;
            }

            $scopeCode = isset($stateRecord['scope_code']) ? (string) $stateRecord['scope_code'] : '';
            if ($scopeCode !== '') {
                $stateByPath[$path]['scope_codes'][$scopeCode] = true;
            }

            if (($stateRecord['is_sensitive'] ?? false) === true) {
                $stateByPath[$path]['has_sensitive_value'] = true;
            }

            if (($stateRecord['is_override'] ?? false) === true) {
                $stateByPath[$path]['has_override'] = true;
            }

            if ($stateByPath[$path]['sample_value'] === null && array_key_exists('value', $stateRecord)) {
                $stateByPath[$path]['sample_value'] = $stateRecord['value'];
                $stateByPath[$path]['sample_value_summary'] = $stateRecord['value_summary'] ?? null;
            }
        }

        ksort($stateByPath);

        $orphanCount = 0;
        foreach ($stateByPath as $path => $aggregate) {
            if (isset($schemaPaths[$path])) {
                continue;
            }

            $result->addOrphan([
                'kind' => 'orphan_config_path',
                'path' => $path,
                'occurrence_count' => $aggregate['occurrence_count'],
                'scopes' => array_values(array_keys($aggregate['scopes'])),
                'scope_ids' => array_values(array_keys($aggregate['scope_ids'])),
                'scope_codes' => array_values(array_keys($aggregate['scope_codes'])),
                'has_sensitive_value' => $aggregate['has_sensitive_value'],
                'has_override' => $aggregate['has_override'],
                'sample_value' => $aggregate['sample_value'],
                'sample_value_summary' => $aggregate['sample_value_summary'],
                'reason' => 'Path exists in core_config_data but was not found in the active admin configuration schema.',
            ]);
            $orphanCount++;
        }

        $result->setSummaryValue('orphan_config_path_count', $orphanCount);
    }
}
