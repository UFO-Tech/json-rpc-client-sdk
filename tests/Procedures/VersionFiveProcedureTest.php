<?php

namespace Ufo\RpcSdk\Tests\Procedures;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Ufo\Component\TransportContracts\AsyncStampDTO;
use Ufo\Component\TransportContracts\AsyncTransportFactory;
use Ufo\Component\TransportContracts\AsyncTransportResolverInterface;
use Ufo\RpcObject\RpcAsyncRequest;
use Ufo\RpcSdk\Procedures\AbstractAsyncProcedure;
use Ufo\RpcSdk\Procedures\AbstractProcedure;
use Ufo\RpcSdk\Procedures\ApiMethod;
use Ufo\RpcSdk\Procedures\CallApiDefinition;
use Ufo\RpcSdk\Procedures\SdkConfigs;

class VersionFiveProcedureTest extends TestCase
{
    public static function syncTransports(): iterable
    {
        yield 'default' => ['sync'];
        yield 'named' => ['sync_internal'];
    }

    #[DataProvider('syncTransports')]
    public function testHeadersAreMergedAndSentToSelectedEndpoint(string $transportName): void
    {
        $configs = $this->createMock(SdkConfigs::class);
        $configs->expects(self::once())->method('getApiEndpoint')->with('Procedures', $transportName)->willReturn('https://example.test/api');
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('{"jsonrpc":"2.0","id":"test-id","result":"pong"}');
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())->method('request')->with('POST', 'https://example.test/api', self::callback(
            static function (array $options): bool {
                self::assertSame(['Authorization' => 'Bearer new', 'X-Keep' => 'keep', 'X-Trace' => 'trace'], $options['headers']);
                self::assertSame('ping', $options['json']['method']);
                self::assertSame('test-id', $options['json']['id']);
                return true;
            }
        ))->willReturn($response);
        $procedure = new class(headers: ['Authorization' => 'Bearer old', 'X-Keep' => 'keep'], requestId: 'test-id', httpClient: $client, transportName: $transportName) extends AbstractProcedure {
            public SdkConfigs $testConfigs;

            protected function setConfigs(CallApiDefinition $apiMethodDef): void
            {
                $this->sdkConfigs = $this->testConfigs;
            }
        };
        $procedure->testConfigs = $configs;
        self::assertSame($procedure, $procedure->withHeaders(['Authorization' => 'Bearer new'])->withHeaders(['X-Trace' => 'trace'])->withHeaders([]));
        self::assertSame('pong', $procedure->ping());
    }

    public static function asyncTransports(): iterable
    {
        yield 'default' => ['rpc_async', 'amqp'];
        yield 'named' => ['rpc_async_kafka', 'kafka'];
    }

    #[DataProvider('asyncTransports')]
    public function testAsyncEnvelopeContainsResolvedStampTokenAndMetadata(string $name, string $scheme): void
    {
        $dsn = $scheme . '://user:password@broker/events';
        $configs = $this->createMock(SdkConfigs::class);
        $configs->expects(self::once())->method('getApiEndpoint')->with('Procedures', $name)->willReturn($scheme . '://{' . $name . '_secret}@broker/events');
        $stamp = new DelayStamp(42);
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('send')->willReturnCallback(
            static function (Envelope $envelope) use ($stamp): Envelope {
                $message = $envelope->getMessage();
                self::assertInstanceOf(RpcAsyncRequest::class, $message);
                self::assertSame('token', $message->token);
                self::assertSame(['trace' => 'new'], $message->meta);
                self::assertSame('events.publish', $message->rpcRequest->toArray()['method']);
                self::assertSame('test-id', $message->rpcRequest->toArray()['id']);
                self::assertSame('hello', $message->rpcRequest->toArray()['params']['payload']);
                self::assertArrayNotHasKey('meta', $message->rpcRequest->toArray()['params']);
                self::assertSame($stamp, $envelope->last(DelayStamp::class));
                return $envelope;
            }
        );
        $resolver = $this->createMock(AsyncTransportResolverInterface::class);
        $resolver->method('getSupportSchemes')->willReturn([$scheme]);
        $resolver->expects(self::once())->method('getTransport')->with($dsn)->willReturn($transport);
        $resolver->expects(self::once())->method('createAsyncStamp')->with(self::callback(
            static fn(AsyncStampDTO $data): bool => $data->asyncDSN === $dsn && $data->highPriority && $data->extra === []
        ))->willReturn($stamp);
        $procedure = new class(new AsyncTransportFactory([$resolver]), 'token', 'user:password', $name, 'test-id') extends AbstractAsyncProcedure {
            public SdkConfigs $testConfigs;

            protected function setConfigs(CallApiDefinition $apiMethodDef): void
            {
                $this->sdkConfigs = $this->testConfigs;
            }

            #[ApiMethod('events.publish')]
            public function publish(string $payload): true
            {
                return $this->requestApi();
            }
        };
        $procedure->testConfigs = $configs;
        self::assertSame($procedure, $procedure->withMeta(['old' => 'value'])->withMeta(['trace' => 'new']));
        self::assertTrue($procedure->publish('hello'));
    }
}
