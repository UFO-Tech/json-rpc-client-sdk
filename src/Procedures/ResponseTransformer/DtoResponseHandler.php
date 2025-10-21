<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\DtoNameExtractorTrait;

class DtoResponseHandler implements IResponseHandler
{
    use DtoNameExtractorTrait;

    public function handle(array $schema, array $parent, mixed $result, SdkResponseCreator $creator): mixed
    {
        $class = $this->extractClassName($schema[T::REF] ?? null);
        return $class::fromArray($result);
    }

    public function canHandle(array $schema, array $parent): bool
    {
        return ($schema[T::REF] ?? false) && !isset($parent[T::ITEMS]) && !isset($parent[T::ONE_OFF]);
    }

}