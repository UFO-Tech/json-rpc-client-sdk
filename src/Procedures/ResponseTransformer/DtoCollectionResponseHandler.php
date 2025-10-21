<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\DtoNameExtractorTrait;

use function array_map;

class DtoCollectionResponseHandler implements IResponseHandler
{
    use DtoNameExtractorTrait;

    public function handle(array $schema, array $parent, mixed $result, SdkResponseCreator $creator): mixed
    {
        $schema = $parent[T::ONE_OFF] ?? [];
        $itemRef = $schema[T::REF] ?? null;
        if (!$itemRef) {
            return $result;
        }

        $class = $this->extractClassName($itemRef);
        return array_map(fn(array $item) => $class::fromArray($item), $result);
    }

    public function canHandle(array $schema, array $parent): bool
    {
        return ($schema[T::REF] ?? false) && ($parent[T::ONE_OFF] ?? false);
    }

}