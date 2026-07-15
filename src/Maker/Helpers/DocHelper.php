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
        if (is_array($paramSchema['schema'][TypeHintResolver::ONE_OFF] ?? null)) {
            $paramSchema['schema'] = DocHelper::getComponentData($paramSchema['schema'], $components);
        }
        if (is_array($paramSchema[TypeHintResolver::ONE_OFF] ?? null)) {
            foreach ($paramSchema[TypeHintResolver::ONE_OFF] as $i => $subSchema) {
                if (is_array($subSchema)) {
                    $paramSchema[TypeHintResolver::ONE_OFF][$i] = DocHelper::getComponentData($subSchema, $components);
                }
            }
        }
        $itemRef = $paramSchema[TypeHintResolver::ITEMS][TypeHintResolver::REF] ?? null;
        if (is_string($itemRef)) {
            $itemPath = str_replace('#/components/', '', $itemRef);
            $itemData = DocHelper::getPath($components, $itemPath, '/', false);
            $itemType = is_array($itemData) ? ($itemData[TypeHintResolver::TYPE] ?? '') : '';
            if (is_array($itemData) && TypeHintResolver::tryFrom($itemType) !== TypeHintResolver::OBJECT) {
                $paramSchema[TypeHintResolver::ITEMS] = $itemData;
            }
        }
        return $paramSchema;
    }
}
