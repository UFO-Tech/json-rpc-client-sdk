<?php

namespace Ufo\RpcSdk\Procedures;


use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Transformer\Transformer;
use Ufo\RpcSdk\Exceptions\ConfigNotFoundException;
use Ufo\RpcSdk\Exceptions\SdkException;
use Ufo\RpcSdk\Interfaces\ISdkMethodClass;

use function count;
use function end;
use function explode;
use function str_replace;

abstract class AbstractAsyncProcedure extends AbstractBaseProcedure implements ISdkMethodClass
{
    protected TransportInterface $transport;

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
     * @throws AbstractRpcErrorException|ExceptionInterface
     */
    protected function requestApi(): true
    {
        $apiMethodDef = $this->callApiMethodDef();

        $nsParts = explode('\\', $apiMethodDef->refClass->getNamespaceName());
        $asyncDSN = $this->sdkConfigs->getApiEndpoint(end($nsParts), false);
        $asyncDSN = str_replace(AsyncTransport::PLACEHOLDER, $this->secretAsync, $asyncDSN);

        $this->transport ??=
            $this->transportFactory->createTransport($asyncDSN, $this->asyncOptions($asyncDSN), new PhpSerializer());
        $request = RpcRequest::fromArray($apiMethodDef->body);

        $request = new RpcRequest(
            $request->getId(),
            $request->getMethod(),
            params: json_decode(Transformer::getDefault()->serialize($request->getParams(), 'json'), true),
            version: $request->getVersion(),
        );

        $env = new Envelope(
            new RpcAsyncRequest($request, $this->token),
            [
                new AmqpStamp(routingKey: $this->getQueue($asyncDSN), attributes: ['delivery_mode' => 2])
            ]
        );

        $this->transport->send($env);

        try {
            RequestResponseStack::addRequest($request, ['async' => true]);
            return true;
        } catch (Throwable $e) {
            throw new SdkException($e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function getQueue(string $dsn): string
    {
        $parts = explode('/', $dsn);
        return count($parts) >= 3 ? end($parts) : 'messages';
    }

    protected function asyncOptions(string $dsn): array
    {
        return [
            'exchange' => [
                'name' => 'queue_exchange',
                'type' => 'direct',
            ],
            'queues' => [
                $this->getQueue($dsn) => [
                    'binding_keys' => [$this->getQueue($dsn)],
                ],
            ],
        ];
    }
}
