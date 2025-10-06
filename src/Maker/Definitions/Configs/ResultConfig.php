<?php

namespace Ufo\RpcSdk\Maker\Definitions\Configs;


use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;

use function sprintf;

readonly class ResultConfig extends TypeConfig
{
    protected const string RESULT = '$this->requestApi()->getResult()';

    public function getMethodBody(): string
    {
        $result = null;
        T::filterSchema($this->schema, function (array $schema, array $parentSchema) use (&$result) {
            if (!$result) $result = $this->resolveResultTransform($schema, $parentSchema);
        });
        return $result ?? static::RESULT;
    }

    protected function resolveResultTransform(array $schema, array $parent): ?string
    {
        if (($schema[T::REF] ?? false) && ($parent[T::ITEMS] ?? false)) {
            return $this->collectionTransform($parent[T::ITEMS]);
        } elseif (($schema[T::REF] ?? false) && ($parent[T::ONE_OFF] ?? false)) {
            return $this->unionTransform($parent[T::ONE_OFF]);
        } elseif ($ref = $schema[T::REF] ?? false) {
            return $this->dtoTransform($ref);
        } else {
            return static::RESULT;
        }
    }

    protected function dtoTransform(string $ref): string
    {
        $class = $this->extractRefClassName($ref);
        return sprintf('%s::fromArray(%s)', $class, static::RESULT);
    }

    protected function collectionTransform(array $itemsSchema): string
    {
        $itemRef = $itemsSchema[T::REF] ?? null;
        if (!$itemRef) {
            return static::RESULT;
        }

        $class = $this->extractRefClassName($itemRef);
        return sprintf('array_map(fn(array $item) => %s::fromArray($item), %s)', $class, static::RESULT);
    }

    protected function unionTransform(array $oneOf): string
    {
        $result = [];
        T::filterSchema($oneOf, function (array $schema, array $parentSchema) use (&$result) {
            if ($schema[T::REF] ?? false) {
                $result[] = $this->extractRefClassName($schema[T::REF]);
            }
        });
        $joined = implode('|', $result);

        return sprintf('DTOTransformer::fromArray(%s, %s)', $joined, static::RESULT);
    }

    private function extractRefClassName(string $ref): string
    {
        $parts = explode('/', $ref);
        return DtoClassDefinition::FOLDER . '\\' . end($parts);
    }

}
