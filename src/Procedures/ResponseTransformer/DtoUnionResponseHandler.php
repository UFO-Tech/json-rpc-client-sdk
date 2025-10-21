<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\DtoNameExtractorTrait;

use function implode;

class DtoUnionResponseHandler implements IResponseHandler
{
    use DtoNameExtractorTrait;

    public function handle(array $schema, array $parent, mixed $result, SdkResponseCreator $creator): mixed
    {
        $schema = $parent[T::ITEMS] ?? [];
        $result = [];
        T::filterSchema($schema, function (array $schemaLeaf, array $parentSchema) use (&$result) {
            if ($schemaLeaf[T::REF] ?? false) {
                $result[] = $this->extractClassName($schemaLeaf[T::REF]);
            }
        });
        $joined = implode('|', $result);

        return DTOTransformer::fromArray($joined, $result);
    }

    public function canHandle(array $schema, array $parent): bool
    {
        return ($schema[T::REF] ?? false) && ($parent[T::ITEMS] ?? false);
    }

}