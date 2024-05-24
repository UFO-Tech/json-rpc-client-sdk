<?php

namespace Ufo\RpcSdk\Procedures;


use ReflectionException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcObject\Transformer\Transformer;
use Ufo\RpcSdk\Exceptions\SdkException;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;

use function rand;
use function str_replace;

use const DEBUG_BACKTRACE_PROVIDE_OBJECT;

abstract class AbstractAsyncProcedure extends AbstractBaseProcedure implements ISdkMethodClass
{
    /**
     * @param string $token
     * @param string $secretAsync format as {user:pass}
     * @param string|int|null $requestId
     * @param string $rpcVersion
     */
    public function __construct(
        protected TransportFactoryInterface  $transportFactory,
        protected string               $token = '',
        protected string               $secretAsync = '',
        protected string|int|null      $requestId = null,
        protected string               $rpcVersion = self::DEFAULT_RPC_VERSION
    )
    {
        parent::__construct($requestId, $rpcVersion);
    }

    /**
     * @return true
     * @throws SdkException
     * @throws AbstractRpcErrorException
     */
    protected function requestApi(): true
    {
        $apiMethodDef = $this->callApiMethodDef();
        /**
         * @var AsyncTransport $asyncTransport
         */
        $asyncTransport = $apiMethodDef->refClass->getAttributes(AsyncTransport::class)[0]->newInstance();
        $asyncDSN = str_replace(AsyncTransport::PLACEHOLDER, $this->secretAsync, $asyncTransport->dsn);

        $transport = $this->transportFactory->createTransport($asyncDSN, [], new PhpSerializer());
        $request = RpcRequest::fromArray($apiMethodDef->body);
        $transport->send(new Envelope(new RpcAsyncRequest($request, $this->token)));

        try {
            RequestResponseStack::addRequest($request, ['async' => true]);
            return true;
        } catch (Throwable $e) {
            throw new SdkException($e->getMessage(), $e->getCode(), $e);
        }
    }

}
