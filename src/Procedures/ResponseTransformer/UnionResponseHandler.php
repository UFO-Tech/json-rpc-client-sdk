<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Throwable;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Exceptions\SdkResultUnionTypeIsBrokedException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Traits\DtoNameExtractorTrait;

use function array_map;
use function count;
use function implode;
use function json_encode;

class UnionResponseHandler implements IResponseHandler
{
    use DtoNameExtractorTrait;

    public function handle(array $schema, mixed $result, callable $transform, SdkResponseCreator $creator): mixed
    {
        $errors = 0;
        foreach ($schema[T::ONE_OFF] as $itemSchema) {
            try {
                $result = $transform($itemSchema, $result);
            } catch (Throwable $e) {
                $errors++;
            }
        }
        if ($errors === count($schema[T::ONE_OFF])) throw new SdkResultUnionTypeIsBrokedException('All union type transformations failed. ' . json_encode($schema));
        return $result;
    }

    public function canHandle(array $schema): bool
    {
        return isset($schema[T::ONE_OFF]);
    }

}