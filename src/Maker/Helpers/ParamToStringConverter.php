<?php

namespace Ufo\RpcSdk\Maker\Helpers;

use function is_array;
use function is_bool;
use function is_null;
use function is_string;

class ParamToStringConverter
{
    public static function defaultValue(mixed $defaultValue): string
    {
        return match ($defaultValue) {
            null => 'null',
            '' => '""',
            [] => '[]',
            true => 'true',
            false => 'false',
            default => static::convert($defaultValue)
        };
    }

    public static function convert(mixed $data): string
    {
        return match (true) {
            is_string($data) => "'$data'",
            is_array($data) => self::convertArray($data),
            is_bool($data) => $data ? 'true' : 'false',
            is_null($data) => 'null',
            default => $data,
        };
    }

    // Додаткова функція для конвертації масивів (як приклад)
    public static function convertArray(array $data): string
    {
        $result = array_map(fn($item) => self::convert($item), $data);
        return '[' . implode(', ', $result) . ']';
    }
}