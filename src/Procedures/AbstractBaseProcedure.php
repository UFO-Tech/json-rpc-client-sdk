<?php

namespace Ufo\RpcSdk\Procedures;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\RpcObject\SpecialRpcParamsEnum;
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
        $this->requestId = $requestId ?? uniqid();
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
        $body = [
            'id' => $this->getRequestId(),
            'jsonrpc' => $this->getRpcVersion(),
            'method' => $procedure->method,
        ];
        $validator = new RpcValidator(Validation::createValidator());
        $addOptions = [];
        if (count($args) > 0) {
            foreach ($args as $i => $arg) {
                $addOptions['params'][$refMethod->getParameters()[$i]->getName()] = $arg;
            }
        }
        try {
            $validator->validateMethodParams($this, $function, $addOptions['params'] ?? []);
        } catch (ConstraintsImposedException $error) {
            throw AbstractRpcErrorException::fromCode($error->getCode(), json_encode($error->getConstraintsImposed()));
        }
        $body += $addOptions;
        if ($this->rpcSpecialParams) {
            $body[SpecialRpcParamsEnum::PREFIX] = $this->rpcSpecialParams->getSpecialParams();
        }
        $definition = new CallApiDefinition(
            $refClass,
            $refMethod,
            $procedure,
            $body
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