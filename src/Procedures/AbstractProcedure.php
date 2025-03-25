<?php

namespace Ufo\RpcSdk\Procedures;


use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Exceptions\ConfigNotFoundException;
use Ufo\RpcSdk\Exceptions\SdkException;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;

use function end;
use function explode;

abstract class AbstractProcedure extends AbstractBaseProcedure implements ISdkMethodClass
{

    /**
     * @param array $headers ['header_key' => 'some_header_string_without_spaces']
     * @param string|int|null $requestId
     * @param string $rpcVersion
     * @param HttpClientInterface|null $httpClient
     * @param array $httpRequestOptions
     */
    public function __construct(
        protected array $headers = [],
        string|int|null $requestId = null,
        string $rpcVersion = self::DEFAULT_RPC_VERSION,
        protected ?HttpClientInterface $httpClient = null,
        array $httpRequestOptions = [],
        ?IRpcSpecialParamHandler $rpcSpecialParams = null
    )
    {
        parent::__construct($requestId, $rpcVersion, $rpcSpecialParams);
        $this->httpClient = $httpClient ?? HttpClient::create($httpRequestOptions);
    }

    /**
     * @return RpcResponse
     * @throws SdkException
     * @throws TransportExceptionInterface
     * @throws AbstractRpcErrorException
     */
    protected function requestApi(): RpcResponse
    {
        $apiMethodDef = $this->callApiMethodDef();
        try {
            $attr = ($apiMethodDef->refClass->getAttributes(ApiUrl::class) ?? [])[0] ?? null;
            $apiUrl = $attr?->newInstance() ?? throw new ConfigNotFoundException();
        } catch (ConfigNotFoundException) {
            $nsParts = explode('\\', $apiMethodDef->refClass->getNamespaceName());
            $apiUrl = new ApiUrl($this->sdkConfigs->getApiEndpoint(end($nsParts)));
        }

        $headers = [];
        if (!empty($this->headers)) {
            $headers += $this->headers;
        }

        $request = $this->httpClient->request(
            $apiUrl->getMethod(),
            $apiUrl->getUrl(),
            [
                'headers' => $headers,
                'json' => $apiMethodDef->body
            ]
        );
        RequestResponseStack::addRequest(RpcRequest::fromArray($apiMethodDef->body), $headers);
        try {
            $response = ResponseCreator::fromJson($request->getContent());
            RequestResponseStack::addResponse($response);
            $response->throwError();

            return $response;
        } catch (Throwable $e) {
            throw new SdkException($e->getMessage(), $e->getCode(), $e);
        }
    }

}
