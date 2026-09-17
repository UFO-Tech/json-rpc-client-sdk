<?php

namespace Ufo\RpcSdk\Procedures\AsyncTransportResolvers;

use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Ufo\RpcSdk\Exceptions\TransportNotFoundException;

use function in_array;
use function parse_url;
use function str_starts_with;

class RPCAsyncTransportFactory
{
    /**
     * @var iterable<AsyncTransportResolverInterface>
     */
    protected array $transportsResolvers = [];

    /**
     * @param iterable<AsyncTransportResolverInterface> $asyncTransportResolvers
     */
    public function __construct(
        protected iterable $asyncTransportResolvers
    ) {}

    /**
     * @throws TransportNotFoundException
     */
    public function getTransportResolver(string $dsn): AsyncTransportResolverInterface
    {
        return $this->transportsResolvers[$dsn] ??= $this->analiseDsn($dsn);
    }

    /**
     * @throws TransportNotFoundException
     */
    protected function analiseDsn($dsn): AsyncTransportResolverInterface
    {
        $scheme = parse_url($dsn)["scheme"] ?? "";

        foreach ($this->asyncTransportResolvers as $asyncTransportResolver) {
            if (in_array($scheme, $asyncTransportResolver->getSupportSchemes())) {
                return $asyncTransportResolver;
            }
        }
        throw TransportNotFoundException::off($scheme);
    }

}