<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\Scanner;

interface CollectorInterface
{
    public function collect(ScanContext $context, ScanResult $result): void;
}
