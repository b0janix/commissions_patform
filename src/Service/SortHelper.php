<?php

declare(strict_types=1);

namespace App\Service;

class SortHelper
{
    private static array $columns;
    public static function compare(array $a, array $b): int
    {
        $compArr1 = [];
        $compArr2 = [];
        foreach (self::$columns as $column) {
            if (isset($a[$column]) && isset($b[$column])) {
                $compArr1[] = $a[$column];
                $compArr2[] = $b[$column];
            }
        }
        return $compArr1 <=> $compArr2;
    }

    public static function sort(array &$array, array $columns = ['id']): void
    {
        self::$columns = $columns;
        usort($array, [self::class, 'compare']);
    }
}
