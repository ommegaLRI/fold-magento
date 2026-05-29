<?php

declare(strict_types=1);

namespace CommerceXray\MagentoXray\StoreGraph;

use JsonSerializable;
use RuntimeException;
use Traversable;

final class JsonlWriter
{
    /**
     * @param iterable<int, mixed> $records
     */
    public function write(string $path, iterable $records): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $directory));
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open JSONL file for writing: %s', $path));
        }

        try {
            foreach ($records as $record) {
                $json = json_encode(
                    $this->normalizeRecord($record),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );

                if ($json === false) {
                    throw new RuntimeException(sprintf(
                        'Unable to encode JSONL record for %s: %s',
                        $path,
                        json_last_error_msg()
                    ));
                }

                fwrite($handle, $json . PHP_EOL);
            }
        } finally {
            fclose($handle);
        }
    }

    private function normalizeRecord(mixed $record): mixed
    {
        if ($record instanceof Entity || $record instanceof Relationship) {
            return $record->toArray();
        }

        if ($record instanceof JsonSerializable) {
            return $record->jsonSerialize();
        }

        if ($record instanceof Traversable) {
            return iterator_to_array($record);
        }

        return $record;
    }
}
