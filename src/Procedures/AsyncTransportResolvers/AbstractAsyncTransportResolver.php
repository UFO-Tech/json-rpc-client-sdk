<?php

namespace Ufo\RpcSdk\Procedures\AsyncTransportResolvers;

use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

abstract class AbstractAsyncTransportResolver implements AsyncTransportResolverInterface
{
    /**
     * @var iterable<TransportInterface>
     */
    protected array $transports = [];

    protected ?SerializerInterface $serializer = null;

    public function __construct(
        protected TransportFactoryInterface $transportFactory,
        ?SerializerInterface $serializer = null
    )
    {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    abstract protected function asyncOptions(string $dsn): array;

    public function getTransportFactory(): TransportFactoryInterface
    {
        return $this->transportFactory;
    }

    public function getTransport(string $dsn): TransportInterface
    {
        return $this->transports[$dsn] ??= $this->getTransportFactory()->createTransport(
            $dsn,
            $this->asyncOptions($dsn),
            $this->serializer
        );
    }

}