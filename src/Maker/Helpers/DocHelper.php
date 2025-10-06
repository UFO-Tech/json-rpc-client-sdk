<?php

namespace Ufo\RpcSdk\Maker\Helpers;


use Ufo\RpcError\RpcDataNotFoundException;

use function array_shift;
use function explode;

class DocHelper
{
    public static function getPath(array $payload, string $path, string $separator = '.', bool $strict = true, mixed $default = null): mixed
    {
        $tokens = explode($separator, $path);
        while (null !== ($token = array_shift($tokens))) {
            if (!isset($payload[$token])) {
                if (!$strict) {
                    $payload = $default;
                    break;
                }
                throw new RpcDataNotFoundException('Parameter not found: ' . $token);
            }

            $payload = $payload[$token];
        }
        return $payload;
    }
}