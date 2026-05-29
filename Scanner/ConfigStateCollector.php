<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\Security\SecretMasker;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

final class ConfigStateCollector implements CollectorInterface
{
    private ResourceConnection $resourceConnection;
    private SecretMasker $secretMasker;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ResourceConnection $resourceConnection,
        SecretMasker $secretMasker,
        StoreManagerInterface $storeManager
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->secretMasker = $secretMasker;
        $this->storeManager = $storeManager;
    }

    public function collect(ScanContext $context, ScanResult $result): void
    {
        unset($context);

        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('core_config_data');
            $columns = array_keys($connection->describeTable($table));
            $selectedColumns = array_values(array_intersect(
                ['config_id', 'scope', 'scope_id', 'path', 'value', 'updated_at'],
                $columns
            ));

            if ($selectedColumns === []) {
                $selectedColumns = ['*'];
            }

            $select = $connection->select()
                ->from($table, $selectedColumns)
                ->order(['path ASC', 'scope ASC', 'scope_id ASC']);

            $rows = $connection->fetchAll($select);
        } catch (Throwable $throwable) {
            $result->addError([
                'collector' => self::class,
                'message' => 'Unable to read core_config_data.',
            ], $throwable);
            $result->setSummaryValue('config_state_count', 0);
            return;
        }

        $scopeCounts = [];
        $sensitiveCount = 0;
        $overrideCount = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $scope = (string) ($row['scope'] ?? 'default');
            $scopeId = (int) ($row['scope_id'] ?? 0);
            $rawValue = $row['value'] ?? null;
            $isSensitive = $this->secretMasker->isSensitivePath($path);
            $maskedValue = $this->secretMasker->mask($path, $rawValue);
            $summary = $this->secretMasker->summarizeValue($rawValue);
            $isOverride = !($scope === 'default' && $scopeId === 0);

            if ($isSensitive) {
                $sensitiveCount++;
            }
            if ($isOverride) {
                $overrideCount++;
            }
            $scopeCounts[$scope] = ($scopeCounts[$scope] ?? 0) + 1;

            $result->addConfigState([
                'kind' => 'config_state',
                'config_id' => isset($row['config_id']) ? (int) $row['config_id'] : null,
                'path' => $path,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'scope_code' => $this->resolveScopeCode($scope, $scopeId),
                'value' => $maskedValue,
                'is_sensitive' => $isSensitive,
                'is_override' => $isOverride,
                'value_summary' => $summary,
                'updated_at' => $row['updated_at'] ?? null,
                'source' => 'core_config_data',
            ]);
        }

        ksort($scopeCounts);

        $result->setSummaryValue('config_state_count', count($rows));
        $result->setSummaryValue('config_state_scope_counts', $scopeCounts);
        $result->setSummaryValue('config_sensitive_value_count', $sensitiveCount);
        $result->setSummaryValue('config_override_count', $overrideCount);
    }

    private function resolveScopeCode(string $scope, int $scopeId): ?string
    {
        if ($scope === 'default') {
            return 'default';
        }

        try {
            if ($scope === 'websites') {
                return (string) $this->storeManager->getWebsite($scopeId)->getCode();
            }

            if ($scope === 'stores') {
                return (string) $this->storeManager->getStore($scopeId)->getCode();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
