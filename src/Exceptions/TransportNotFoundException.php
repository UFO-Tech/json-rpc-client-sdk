<?php

namespace Ufo\RpcSdk\Exceptions;

use Exception;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\AbstractAsyncTransportResolver;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\AsyncTransportResolverInterface;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\RPCAsyncTransportFactory;

use function sprintf;

class TransportNotFoundException extends Exception
{
    protected const string MSG = 'Resolver not found for transport: "%s". Implement %s or extend %s and add in %s.';

    public static function off(string $dsn): static
    {
        return new static(sprintf(
            static::MSG,
            $dsn,
            AsyncTransportResolverInterface::class,
            AbstractAsyncTransportResolver::class,
            RPCAsyncTransportFactory::class
        ));
    }

}
