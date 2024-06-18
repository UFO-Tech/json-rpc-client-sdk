<?php

namespace Ufo\RpcSdk\Procedures;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Rules\Validator\ConstraintsImposedException;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Exceptions\SdkException;

use function count;
use function debug_backtrace;
use function json_encode;
use function uniqid;

use const DEBUG_BACKTRACE_PROVIDE_OBJECT;

abstract class AbstractBaseProcedure
{
    const DEFAULT_RPC_VERSION = '2.0';

    public function __construct(
        protected string|int|null      $requestId,
        protected string               $rpcVersion,
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

        return new CallApiDefinition(
            $refClass,
            $refMethod,
            $procedure,
            $body
        );
    }

    /**
     * @return int|string
     */
    public function getRequestId(): int|string
    {
        return $this->requestId;
    }

    /**
     * @param int|string $requestId
     * @return $this
     */
    public function setRequestId(int|string $requestId): static
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    public function getRpcVersion(): string
    {
        return $this->rpcVersion;
    }

    /**
     * @param string $version
     * @return $this
     */
    public function setRpcVersion(string $version): static
    {
        $this->rpcVersion = $version;
        return $this;
    }
}