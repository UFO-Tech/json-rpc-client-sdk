<?php

namespace Ufo\RpcSdk\Maker;

use function preg_replace;
use function strtoupper;

enum StringTransformerEnum: string
{
    case EMPTY = '';

    public static function alias(string $value): string
    {
        return match ($value) {
            '--', '' => self::EMPTY->name,
            default => $value,
        };
    }

    public static function transformName(string $value): string
    {
        $valueName = self::alias($value);
        if ($valueName === $value) {
            if (ctype_digit($value[0])) {
                $value = 'case_' . $value;
            }
            $valueName =  strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', self::transliterate($value)));
        }
        return $valueName;
    }

    public static function transliterate(string $text): string
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
        return preg_replace('/\W/', '', $transliterator->transliterate($text));
    }
}
