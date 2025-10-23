<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Exceptions\BadParamException;
use Ufo\DTO\Exceptions\NotSupportDTOException;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Exceptions\SdkResponseHandlerException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\DtoNameExtractorTrait;

use function is_subclass_of;

class DtoResponseHandler implements IResponseHandler
{
    use DtoNameExtractorTrait;

    /**
     * @throws SdkResponseHandlerException
     */
    public function handle(array $schema, mixed $result, callable $transform, SdkResponseCreator $creator): object
    {
        $class = $schema[T::CLASS_FQCN] ?? $this->extractClassName($schema[T::REF] ?? '');
        try {
            if (is_subclass_of($class, IArrayConstructible::class)) {
                return $class::fromArray($result);
            } else {
                return DTOTransformer::fromArray(
                    classFQCN: $class,
                    data: $result,
                    namespaces: [
                        DTOTransformer::DTO_NS_KEY => $creator->namespace . '\\' . DtoClassDefinition::FOLDER,
                    ]
                );
            }
        } catch (NotSupportDTOException|BadParamException $e) {
            throw new SdkResponseHandlerException($e->getMessage(), previous: $e);
        }
    }

    public function canHandle(array $schema): bool
    {
        return isset($schema[T::REF]) || isset($schema[T::CLASS_FQCN]);
    }

}