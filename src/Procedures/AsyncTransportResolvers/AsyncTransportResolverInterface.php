<?php

namespace Ufo\RpcSdk\Procedures\AsyncTransportResolvers;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

interface AsyncTransportResolverInterface
{
    public function getTransportFactory(): TransportFactoryInterface;
    public function getTransport(string $dsn): TransportInterface;

    public function createAsyncStamp(AsyncStampDTO $asyncStampData): StampInterface;
    public function getSupportSchemes(): array;
}