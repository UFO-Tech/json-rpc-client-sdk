<?php

namespace Ufo\RpcSdk\Maker\Helpers;


use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcError\RpcDataNotFoundException;

use function array_shift;
use function explode;
use function str_replace;

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

    public static function getComponentData(array $paramSchema, array $components): array
    {
        if  ($ref = $paramSchema[TypeHintResolver::REF] ?? false) {
            $ref = str_replace('#/components/', '', $ref);
            $data = DocHelper::getPath($components, $ref, '/');
            if (TypeHintResolver::tryFrom($data[TypeHintResolver::TYPE] ?? '') !== TypeHintResolver::OBJECT) {
                $paramSchema = $data;
            }
        }
        if ($paramSchema['schema'][TypeHintResolver::REF] ?? false) {
            $paramSchema['schema'] = DocHelper::getComponentData($paramSchema['schema'], $components);
        }
        return $paramSchema;
    }
}