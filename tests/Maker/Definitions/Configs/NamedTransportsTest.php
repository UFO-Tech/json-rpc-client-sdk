<?php

namespace Ufo\RpcSdk\Tests\Maker\Definitions\Configs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Ufo\RpcSdk\Maker\Definitions\Configs\ConfigsHolder;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;

class NamedTransportsTest extends TestCase
{
    public static function transports(): iterable
    {
        yield 'missing metadata' => [[], [], false];
        yield 'sync only' => [['transport' => ['sync' => ['scheme' => 'http']]], ['sync' => ['scheme' => 'http']], false];
        yield 'legacy async key' => [['transport' => ['async' => ['scheme' => 'amqp']]], ['async' => ['scheme' => 'amqp']], false];
        yield 'async default' => [['transport' => ['rpc_async' => ['scheme' => 'amqp']]], ['rpc_async' => ['scheme' => 'amqp']], true];
        yield 'named async only' => [['transport' => ['rpc_async_kafka' => ['scheme' => 'kafka']]], ['rpc_async_kafka' => ['scheme' => 'kafka']], true];
        yield 'prefix must be at start' => [['transport' => ['custom_rpc_async' => []]], ['custom_rpc_async' => []], false];
    }

    #[DataProvider('transports')]
    public function testReadsTransportsAndDetectsAsyncPrefix(array $metadata, array $expected, bool $async): void
    {
        $reader = $this->createMock(IDocReader::class);
        $reader->method('getApiDocumentation')->willReturn([
            'openrpc' => '1.3.2',
            'servers' => [['url' => 'https://example.test/api', 'x-ufo' => $metadata]],
            'methods' => [],
        ]);
        $holder = new ConfigsHolder($reader, __DIR__, 'test', cache: new ArrayAdapter());

        self::assertSame($expected, $holder->getTransports());
        self::assertSame($async, $holder->haveAsyncTransport());
    }
}
