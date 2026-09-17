<?php

namespace Ufo\RpcSdk\Procedures;


use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcTransport;
use Ufo\RpcObject\SpecialRpcParamsEnum;
use Ufo\RpcSdk\Exceptions\SdkException;
use Ufo\RpcSdk\Exceptions\TransportNotFoundException;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;
use Ufo\RpcObject\RPC;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\AsyncStampDTO;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\RPCAsyncTransportFactory;

use function end;
use function explode;
use function str_replace;

abstract class AbstractAsyncProcedure extends AbstractBaseProcedure implements ISdkMethodClass
{

    /**
     * @param RPCAsyncTransportFactory $asyncTransportFactory
     * @param string $token
     * @param string $secretAsync format as {user:pass}
     * @param string|int|null $requestId
     * @param string $rpcVersion
     */
    public function __construct(
        protected RPCAsyncTransportFactory $asyncTransportFactory,
        protected string               $token = '',
        protected string               $secretAsync = '',
        protected string               $transportName = RpcTransport::ASYNC_PREFIX,
        protected string|int|null      $requestId = null,
        protected string               $rpcVersion = self::DEFAULT_RPC_VERSION
    )
    {
        parent::__construct($requestId, $rpcVersion);
    }

    /**
     * @return true
     * @throws SdkException
     * @throws AbstractRpcErrorException|ExceptionInterface|TransportNotFoundException
     */
    protected function requestApi(): true
    {
        $apiMethodDef = $this->callApiMethodDef();

        $nsParts = explode('\\', $apiMethodDef->refClass->getNamespaceName());
        $asyncDSN = $this->sdkConfigs->getApiEndpoint(end($nsParts), $this->transportName);
        $asyncDSN = str_replace(AsyncTransport::getSecretPlaceholder($this->transportName), $this->secretAsync, $asyncDSN);

        $rpcAsyncTransportResolver = $this->asyncTransportFactory->getTransportResolver($asyncDSN);

        $env = new Envelope(
            new RpcAsyncRequest($apiMethodDef->rpcRequest, $this->token, $this->meta),
            [
                $rpcAsyncTransportResolver->createAsyncStamp(new AsyncStampDTO($asyncDSN))
            ]
        );

        $rpcAsyncTransportResolver->getTransport($asyncDSN)->send($env);

        try {
            RequestResponseStack::addRequest($apiMethodDef->rpcRequest, ['async' => true]);
            return true;
        } catch (Throwable $e) {
            throw new SdkException($e->getMessage(), $e->getCode(), $e);
        }
    }

    #[RPC\IgnoreApi]
    public function asyncTimeout(int $timeout): static
    {
        $this->rpcSpecialParams->setParam(
            SpecialRpcParamsEnum::TIMEOUT->value,
            $timeout
        );
        return $this;
    }

    #[RPC\IgnoreApi]
    public function callbackTo(string $callbackUrl): static
    {
        $this->rpcSpecialParams->setParam(
            SpecialRpcParamsEnum::CALLBACK->value,
            $callbackUrl
        );
        return $this;
    }

}
