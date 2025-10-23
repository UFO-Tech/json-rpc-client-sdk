<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Exceptions\BadParamException;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Exceptions\SdkResponseHandlerException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\EnumNameExtractorTrait;
use UnitEnum;

class EnumResponseHandler implements IResponseHandler
{
    use EnumNameExtractorTrait;

    /**
     * @throws SdkResponseHandlerException
     */
    public function handle(array $schema, mixed $result, callable $transform, SdkResponseCreator $creator): UnitEnum
    {
        try {
            $class = $this->getEnumName($schema, $creator->namespace);
            $result = DTOTransformer::transformEnum($class, $result);
            if (!$result instanceof UnitEnum) {
                throw new BadParamException('Value: ' . $result . ' not transform to enum: ' . $class);
            }
            return $result;
        } catch (BadParamException $e) {
            throw new SdkResponseHandlerException($e->getMessage(), previous: $e);
        }
    }

    public function canHandle(array $schema): bool
    {
        return isset($schema[EnumResolver::ENUM]) || isset($schema[EnumResolver::ENUM_KEY]);
    }

}