<?php

namespace Ufo\RpcSdk\Procedures;


use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Rules\Validator\ConstraintsImposedException;
use Ufo\RpcObject\Rules\Validator\RpcValidator;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Exceptions\SdkException;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function json_encode;

abstract class AbstractProcedure implements ISdkMethodClass
{
    const DEFAULT_RPC_VERSION = '2.0';

    /**
     * @param array $headers ['header_key' => 'some_header_string_without_spaces']
     * @param string|int|null $requestId
     * @param string $rpcVersion
     * @param HttpClientInterface|null $httpClient
     * @param array $httpRequestOptions
     */
    public function __construct(
        protected array                $headers = [],
        protected string|int|null      $requestId = null,
        protected string               $rpcVersion = self::DEFAULT_RPC_VERSION,
        protected ?HttpClientInterface $httpClient = null,
        array                          $httpRequestOptions = [],
    )
    {
        $this->requestId = $requestId ?? uniqid();
        $this->httpClient = $httpClient ?? HttpClient::create($httpRequestOptions);
    }

    /**
     * @return RpcResponse
     * @throws SdkException
     * @throws ReflectionException
     * @throws TransportExceptionInterface
     */
    protected function requestApi(): RpcResponse
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1];
        $function = $backtrace['function'];
        $args = $backtrace['args'];
        $refClass = new ReflectionClass(static::class);
        $refMethod = $refClass->getMethod($function);
        $procedure = $refMethod->getAttributes(ApiMethod::class)[0]->newInstance();
        $body = [
            'id' => $this->getRequestId(),
            'jsonrpc' => $this->getRpcVersion(),
            'method' => $procedure->getMethod(),
        ];

        $apiUrl = $refClass->getAttributes(ApiUrl::class)[0]->newInstance();

        $validator = new RpcValidator(Validation::createValidator());
        $addOptions = [];
        if (count($args) > 0) {
            foreach ($args as $i => $arg) {
                $addOptions['params'][$refMethod->getParameters()[$i]->getName()] = $arg;
            }
        }
        try {
            $validator->validateMethodParams($this, $function, $addOptions['params']);
        } catch (ConstraintsImposedException $error) {
            throw AbstractRpcErrorException::fromCode($error->getCode(), json_encode($error->getConstraintsImposed()));
        }
        $body += $addOptions;

        $headers = [];
        if (!empty($this->headers)) {
            $headers += $this->headers;
        }

        $request = $this->httpClient->request(
            $apiUrl->getMethod(),
            $apiUrl->getUrl(),
            [
               'headers' => $headers,
               'json' => $body
            ]
        );
        RequestResponseStack::addRequest(RpcRequest::fromArray($body), $headers);
        try {
            $response = ResponseCreator::fromJson($request->getContent());
            RequestResponseStack::addResponse($response);
            $response->throwError();

            return $response;
        } catch (Throwable $e) {
            throw new SdkException($e->getMessage(), $e->getCode(), $e);
        }
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
