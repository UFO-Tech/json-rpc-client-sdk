<?php

namespace Ufo\RpcSdk\Procedures;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\RpcObject\SpecialRpcParamsEnum;
use Ufo\RpcObject\Transformer\Transformer;
use Ufo\RpcSdk\Exceptions\SdkException;
use Ufo\RpcObject\RPC;

use function count;
use function debug_backtrace;
use function json_encode;
use function pathinfo;
use function uniqid;

use const DEBUG_BACKTRACE_PROVIDE_OBJECT;

abstract class AbstractBaseProcedure
{
    const string DEFAULT_RPC_VERSION = '2.0';
    protected ?SdkConfigs $sdkConfigs = null;

    public function __construct(
        protected string|int|null $requestId,
        protected string $rpcVersion,
        protected ?IRpcSpecialParamHandler $rpcSpecialParams = null
    )
    {
        $this->rpcSpecialParams ??= new RpcSpecialParamHandler();
        $this->setId($requestId);
    }

    /**
     * @return RpcResponse|true
     * @throws SdkException
     * @throws ReflectionException
     * @throws TransportExceptionInterface
     */
    abstract protected function requestApi(): RpcResponse|true;

    protected function callApiMethodDef(): CallApiDefinition
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];
        $function = $backtrace['function'];
        $args = $backtrace['args'];
        $refClass = new ReflectionClass(static::class);
        $refMethod = $refClass->getMethod($function);
        /**
         * @var ApiMethod $procedure
         */
        $procedure = $refMethod->getAttributes(ApiMethod::class)[0]->newInstance();
        $params = [];
        $validator = new RpcValidator(Validation::createValidator());
        if (count($args) > 0) {
            foreach ($args as $i => $arg) {
                $params[$refMethod->getParameters()[$i]->getName()] = $arg;
            }
        }
        try {
            $validator->validateMethodParams($this, $function, $params);
        } catch (ConstraintsImposedException $error) {
            throw AbstractRpcErrorException::fromCode($error->getCode(), json_encode($error->getConstraintsImposed()));
        }
        if ($this->rpcSpecialParams) {
            $params[SpecialRpcParamsEnum::PREFIX] = $this->rpcSpecialParams->getSpecialParams();
        }
        $definition = new CallApiDefinition(
            $refClass,
            $refMethod,
            $procedure,
            new RpcRequest(
                id: $this->getRequestId(),
                method: $procedure->method,
                params: json_decode(Transformer::getDefault()->serialize($params, 'json'), true),
                version: $this->getRpcVersion()
            )
        );
        $this->setConfigs($definition);
        return $definition;
    }

    protected function setConfigs(CallApiDefinition $apiMethodDef): void
    {
        if (!$this->sdkConfigs) {
            $this->sdkConfigs = new SdkConfigs(pathinfo($apiMethodDef->refClass->getFileName())['dirname'] . '/..');
        }

        if (
            empty($this->sdkConfigs->getConfigs())
            && empty($this->sdkConfigs->getConfigs(true))
            && $parentClass = $apiMethodDef->refClass->getParentClass()
        ) {
            $this->sdkConfigs = new SdkConfigs(pathinfo($parentClass->getFileName())['dirname'] . '/..');
        }
    }

    /**
     * @return int|string
     */
    #[RPC\IgnoreApi]
    public function getRequestId(): int|string
    {
        return $this->requestId;
    }

    /**
     * @param int|string $requestId
     * @return $this
     */
    #[RPC\IgnoreApi]
    public function setRequestId(int|string $requestId): static
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    #[RPC\IgnoreApi]
    public function getRpcVersion(): string
    {
        return $this->rpcVersion;
    }

    #[RPC\IgnoreApi]
    public function withoutCache(): static
    {
        $this->rpcSpecialParams->setParam(
            SpecialRpcParamsEnum::IGNORE_CACHE->value,
            true
        );
        return $this;
    }

    #[RPC\IgnoreApi]
    public function withCache(): static
    {
        $this->rpcSpecialParams->setParam(
            SpecialRpcParamsEnum::IGNORE_CACHE->value,
            false
        );
        return $this;
    }

    #[RPC\IgnoreApi]
    public function rayId(string|int $rayId): static
    {
        $this->rpcSpecialParams->setParam(
            SpecialRpcParamsEnum::PARENT_REQUEST->value,
            $rayId
        );
        return $this;
    }

    #[RPC\IgnoreApi]
    public function resetConfig(): static
    {
        $this->rpcSpecialParams->resetParams();
        return $this;
    }

    #[RPC\IgnoreApi]
    public function setId(string|int|null $requestId = null): static
    {
        $this->requestId = $requestId ?? uniqid();
        return $this;
    }

    /**
     * @param string $version
     * @return $this
     */
    #[RPC\IgnoreApi]
    public function setRpcVersion(string $version): static
    {
        $this->rpcVersion = $version;
        return $this;
    }

    /**
     * @method 'ping'
     * @return string
     * @throws ReflectionException
     * @throws SdkException
     * @throws TransportExceptionInterface
     */
    #[ApiMethod('ping')]
    #[RPC\IgnoreApi]
    public function ping(): string
    {
        return $this->requestApi()->getResult();
    }
}