<?php
namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use phpDocumentor\Reflection\DocBlockFactory;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Helpers\TypeHintResolver as T;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Procedures\CallApiDefinition;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;

use function is_array;

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

        if (!$rpcResponse->getError()) {
            $result = $instance->resolveResultTransform(
                $instance->resultSchema,
                $rpcResponse->getResult(true)
            );
        }


        return new RpcResponse(
            $rpcResponse->getId(),
            $result ?? null,
            $rpcResponse->getError() ?? null,
            $rpcResponse->getVersion(),
            $rpcResponse->getRequestObject(),
            $rpcResponse->getCacheInfo(),
        );
    }

    protected function resolveResultTransform(array $schema, mixed $data): mixed
    {
        $result = $data;
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($schema)) {
                $result = $handler->handle(
                    $schema,
                    $data,
                    fn(array $s, mixed $d) => $this->resolveResultTransform($s, $d),
                    $this
                );
                break;
            }
        }
        return $result;
    }
}