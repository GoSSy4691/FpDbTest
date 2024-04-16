<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $index = 0;
        $query = preg_replace_callback('/\?(d|f|a|#|s)?/', function($matches) use (&$args, &$index) {
            $type = $matches[1] ?? 's';
            $value = $args[$index++] ?? $this->skip();
            return $this->formatValue($value, $type);
        }, $query);

        return $this->handleConditionalBlocks($query);
    }

    private function formatValue($value, string $type): string
    {
        if ($value === $this->skip()) {
            return 'NULL';
        }

        switch ($type) {
            case 'd':
                return intval($value);
            case 'f':
                return floatval($value);
            case 'a':
                return $this->formatAssocArray($value);
            case '#':
                return $this->formatColumnNames($value);
            case 's':
                return $this->escape($value);
            default:
                throw new Exception("Unsupported placeholder type: $type");
        }
    }

    private function formatAssocArray(array $arr): string
    {
        if ($this->is_assoc($arr)) {
            return implode(', ', array_map(function($k, $v) { return "`$k` = " . $this->escape($v); }, array_keys($arr), $arr));
        } else {
            return implode(', ', array_map([$this, 'escape'], $arr));
        }
    }

    private function formatColumnNames($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(function($v) { return "`$v`"; }, $value));
        } else {
            return "`$value`";
        }
    }

    private function handleConditionalBlocks(string $query): string
    {
        return preg_replace_callback('/{[^{}]*}/', function($matches) {
            if (strpos($matches[0], 'NULL') !== false) {
                return '';
            }
            return str_replace(['{', '}'], '', $matches[0]);
        }, $query);
    }

    private function escape($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_string($value)) {
            return "'" . $this->mysqli->real_escape_string($value) . "'";
        } elseif (is_numeric($value)) {
            return $value;
        }
        throw new Exception("Invalid data type for SQL operation");
    }

    private function is_assoc(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function skip(): string
    {
        return 'NULL';
    }
}
