<?php

namespace Ufo\RpcSdk\Procedures;

use phpDocumentor\Reflection\DocBlockFactory;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\EnumResolver;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Maker\Definitions\DtoClassDefinition;
use Ufo\RpcSdk\Maker\Definitions\EnumDefinition;

use function end;
use function explode;
use function implode;
use function sprintf;

class SdkResponseCreator extends ResponseCreator
{
    protected static array $instances = [];
    protected array $resultSchema;
    protected string $namespace;

    public function __construct(
        readonly public CallApiDefinition $apiDefinition,
    ) {
        $docComment = $apiDefinition->refMethod->getDocComment();
        $docReflection = DocBlockFactory::createInstance()->create($docComment);
        $docType = (string)$docReflection->getTagsByName('return')[0] ?? T::MIXED->value;
        $this->namespace = $apiDefinition->refClass->getNamespaceName();

        $this->resultSchema = T::typeDescriptionToJsonSchema($docType, [
            DTOTransformer::DTO_NS_KEY => $apiDefinition->refClass->getNamespaceName()
        ]);
    }

    public static function fromApiResponse(string $json, CallApiDefinition $apiDefinition): RpcResponse
    {
        $rpcResponse = parent::fromJson($json);

        $instance = static::$instances[$apiDefinition->method->method] ??= new static($apiDefinition);

        $result = $instance->getMethodBody($rpcResponse->getResult(true));
        return new RpcResponse(
            $rpcResponse->getId(),
            $result ?? null,
            $rpcResponse->getError() ?? null,
            $rpcResponse->getVersion(),
            $rpcResponse->getRequestObject(),
            $rpcResponse->getCacheInfo(),
        );
    }

    protected function getMethodBody(mixed $data): mixed
    {
        T::filterSchema($this->resultSchema, function (array $schema, array $parentSchema) use (&$result, &$data) {
            if (!$result) $data = $this->resolveResultTransform($schema, $parentSchema, $data);
        });
        return $data;
    }

    protected function resolveResultTransform(array $schema, array $parent, mixed $data): mixed
    {
        if (EnumResolver::schemaHasEnum($schema)) {
            $enumName = $this->extractRefClassName(EnumResolver::findEnumNameInJsonSchema($schema), true);
            return DTOTransformer::transformEnum($this->namespace . '\\'. $enumName, $data);
        } elseif (($schema[T::REF] ?? false) && ($parent[T::ITEMS] ?? false)) {
            return $this->collectionTransform($parent[T::ITEMS], $data);
        } elseif (($schema[T::REF] ?? false) && ($parent[T::ONE_OFF] ?? false)) {
            return $this->unionTransform($parent[T::ONE_OFF], $data);
        } elseif ($ref = $schema[T::REF] ?? false) {
            return $this->dtoTransform($ref, $data);
        } else {
            return $data;
        }
    }

    protected function dtoTransform(string $ref, mixed $data): string
    {
        $class = $this->extractRefClassName($ref);
        return $class::fromArray($data);
    }

    protected function collectionTransform(array $itemsSchema, mixed $data): mixed
    {
        $itemRef = $itemsSchema[T::REF] ?? null;
        if (!$itemRef) {
            return $data;
        }

        $class = $this->extractRefClassName($itemRef);
        return array_map(fn(array $item) => $class::fromArray($item), $data);
    }

    protected function unionTransform(array $oneOf, mixed $data): string
    {
        $result = [];
        T::filterSchema($oneOf, function (array $schema, array $parentSchema) use (&$result) {
            if ($schema[T::REF] ?? false) {
                $result[] = $this->extractRefClassName($schema[T::REF]);
            }
        });
        $joined = implode('|', $result);

        return DTOTransformer::fromArray($joined, $data);
    }

    /**
     * @param string $ref
     * @return class-string
     */
    private function extractRefClassName(string $ref, bool $enum = false): string
    {
        $parts = explode('/', $ref);
        return ($enum ? EnumDefinition::FOLDER : DtoClassDefinition::FOLDER) . '\\' . end($parts);
    }

}