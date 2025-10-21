<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\EnumNameExtractorTrait;

class EnumResponseHandler implements IResponseHandler
{
    use EnumNameExtractorTrait;

    public function handle(array $schema, array $parent, mixed $result, SdkResponseCreator $creator): mixed
    {
        $class = $this->getEnumName($schema, $creator->namespace);
        return DTOTransformer::transformEnum($class, $result);
    }

    public function canHandle(array $schema, array $parent): bool
    {
        return (EnumResolver::schemaHasEnum($schema)) && !isset($parent[T::ITEMS]) && !isset($parent[T::ONE_OFF]);
    }

}