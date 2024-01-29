<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\FileException;
use DateTime;
use Exception;
use Generator;

class FileHelper
{
    private int $offset = 0;
    private int $length = 100;
    public const KEYS = [
        'date',
        'user_id',
        'user_type',
        'operation_type',
        'operation_amount',
        'operation_currency',
        'day_of_the_week',
        'id'
    ];

    public function generateFilePath(string $fileName): string
    {
        return __DIR__ . "/input/$fileName";
    }

    public function getFilePath(string $fileName): string
    {
        $filePath = $this->generateFilePath($fileName);

        if (!file_exists($filePath)) {
            LogHelper::logError("The file $filePath does not exist");

            return '';
        }

        return $filePath;
    }

    /**
     * @throws Exception
     */
    public function getCSVAsArray(string $filePath, int $offset, int $length): array
    {
        $csvArray = [];
        $j = 0;

        $this->offset = $offset;
        $this->length = $length;

        foreach ($this->lazyLoadCSV($filePath) as $row) {
            $dt = new DateTime($row[0]);
            $row[] = (int) $dt->format('N');
            $row[] = ++$j;
            $row[0] = $dt;
            $row[1] = (int) $row[1];
            $row[4] = (float) $row[4];
            $csvArray[] = array_combine(self::KEYS, $row);
        }

        return $csvArray;
    }

    private function lazyLoadCSV($filePath): Generator
    {
        if (!file_exists($filePath)) {
            LogHelper::logError("The file $filePath does not exist");

            exit("The file $filePath does not exist");
        }

        $handle = fopen($filePath, 'r');

        $i = 1;

        while (($row = fgetcsv($handle, 1000, ",")) !== false) {
            if ($i++ < $this->offset) {
                continue;
            }

            if ($i === ($this->offset + $this->length)) {
                break;
            }

            yield $row;
        }

        fclose($handle);
    }
}
