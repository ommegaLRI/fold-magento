<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

use CommerceXray\MagentoXray\Output\BundleWriter;
use Throwable;

final class ScanRunner
{
    private BundleWriter $bundleWriter;
    /** @var CollectorInterface[] */
    private array $collectors;

    /**
     * @param CollectorInterface[] $collectors
     */
    public function __construct(BundleWriter $bundleWriter, array $collectors = [])
    {
        $this->bundleWriter = $bundleWriter;
        $this->collectors = $collectors;
    }

    public function run(ScanContext $context): ScanResult
    {
        $result = new ScanResult();
        $result->setMetadataValue('runner_started_at', gmdate(DATE_ATOM));
        $result->setMetadataValue('collector_count', count($this->collectors));

        foreach ($this->collectors as $collector) {
            if (!$collector instanceof CollectorInterface) {
                $result->addError([
                    'collector' => is_object($collector) ? $collector::class : gettype($collector),
                    'message' => 'Configured collector does not implement CollectorInterface.',
                ]);
                continue;
            }

            $collectorName = $collector::class;
            $startedAt = microtime(true);

            try {
                $collector->collect($context, $result);
                $result->incrementSummaryValue('collectors_completed');
            } catch (Throwable $throwable) {
                $result->incrementSummaryValue('collectors_failed');
                $result->addError([
                    'collector' => $collectorName,
                    'message' => $throwable->getMessage(),
                ], $throwable);
            } finally {
                $result->setMetadataValue(
                    'last_collector_duration_ms',
                    (int) round((microtime(true) - $startedAt) * 1000)
                );
            }
        }

        $result->setMetadataValue('runner_finished_at', gmdate(DATE_ATOM));
        $this->bundleWriter->write($context, $result);

        return $result;
    }
}
