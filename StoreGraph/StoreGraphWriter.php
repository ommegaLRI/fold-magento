<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\StoreGraph;

use CommerceXray\MagentoXray\Scanner\ScanResult;

final class StoreGraphWriter
{
    private JsonlWriter $jsonlWriter;

    public function __construct(JsonlWriter $jsonlWriter)
    {
        $this->jsonlWriter = $jsonlWriter;
    }

    public function write(string $scanDir, ScanResult $result): void
    {
        $this->jsonlWriter->write(
            $scanDir . DIRECTORY_SEPARATOR . 'storegraph.entities.jsonl',
            $result->getEntities()
        );

        $this->jsonlWriter->write(
            $scanDir . DIRECTORY_SEPARATOR . 'storegraph.relationships.jsonl',
            $result->getRelationships()
        );
    }
}
