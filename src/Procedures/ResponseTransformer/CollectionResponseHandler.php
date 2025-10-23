<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\DtoNameExtractorTrait;

use function array_map;
use function implode;

class CollectionResponseHandler implements IResponseHandler
{
    use DtoNameExtractorTrait;

    public function handle(array $schema, mixed $result, callable $transform, SdkResponseCreator $creator): array
    {
        foreach ($result as $key => $item) {
            $result[$key] = $transform($schema[T::ITEMS] ?? $schema[T::ADDITIONAL_PROPERTIES] ?? [], $item);
        }
        return $result;
    }

    public function canHandle(array $schema): bool
    {
        return isset($schema[T::ITEMS])
               || (isset($schema[T::ADDITIONAL_PROPERTIES])
                   && !isset($schema[T::CLASS_FQCN]));
    }

}