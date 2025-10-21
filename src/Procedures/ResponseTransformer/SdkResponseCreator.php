<?php
namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use phpDocumentor\Reflection\DocBlockFactory;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Procedures\CallApiDefinition;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;

class SdkResponseCreator extends ResponseCreator
{
    protected static array $instances = [];

    readonly public  array $resultSchema;

    readonly public string $namespace;

    /**
     * @param CallApiDefinition $apiDefinition
     * @param IResponseHandler[] $handlers
     */
    public function __construct(
        readonly public CallApiDefinition $apiDefinition,
        protected iterable $handlers = []
    ) {
        $docComment = $apiDefinition->refMethod->getDocComment();
        $docReflection = DocBlockFactory::createInstance()->create($docComment);
        $docType = (string)$docReflection->getTagsByName('return')[0] ?? T::MIXED->value;
        $this->namespace = $apiDefinition->refClass->getNamespaceName();
        $this->resultSchema = T::typeDescriptionToJsonSchema($docType, [
            DTOTransformer::DTO_NS_KEY => $apiDefinition->refClass->getNamespaceName()
        ]);
    }

    public static function fromApiResponse(
        string $json,
        CallApiDefinition $apiDefinition,
        iterable $handlers = []
    ): RpcResponse
    {
        $rpcResponse = parent::fromJson($json);
        $instance = static::$instances[$apiDefinition->method->method] ??= new static($apiDefinition, $handlers);
        $result = $instance->process($rpcResponse->getResult(true));

        return new RpcResponse(
            $rpcResponse->getId(),
            $result ?? null,
            $rpcResponse->getError() ?? null,
            $rpcResponse->getVersion(),
            $rpcResponse->getRequestObject(),
            $rpcResponse->getCacheInfo(),
        );
    }
    protected function process(mixed $data): mixed
    {
        T::filterSchema($this->resultSchema, function (array $schema, array $parentSchema) use (&$result, &$data) {
            if (!$result) $data = $this->resolveResultTransform($schema, $parentSchema, $data);
        });
        return $data;
    }

    protected function resolveResultTransform(array $schema, array $parent, mixed $result): mixed
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($schema, $parent)) {
                $result = $handler->handle($schema, $parent, $result, $this);
                break;
            }
        }
        return $result;
    }

}