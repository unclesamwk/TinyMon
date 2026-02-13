<?php

namespace App\services;

use flight\database\PdoWrapper;

class Database extends PdoWrapper
{
    public function fetchRow(string $sql, array $params = []): ?array
    {
        $result = parent::fetchRow($sql, $params);
        $result = $this->unwrap($result);
        return is_array($result) && !empty($result) ? $result : null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $results = parent::fetchAll($sql, $params);
        return array_map(function ($row) {
            return $this->unwrap($row);
        }, is_array($results) ? $results : []);
    }

    private function unwrap(mixed $value): mixed
    {
        if ($value instanceof \flight\util\Collection) {
            return $value->getData();
        }
        if (is_object($value) && method_exists($value, 'getData')) {
            return $value->getData();
        }
        return $value;
    }
}
