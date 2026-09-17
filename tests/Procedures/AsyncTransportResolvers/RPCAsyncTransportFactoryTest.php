<?php

namespace Ufo\RpcSdk\Tests\Procedures\AsyncTransportResolvers;

use ArrayIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Exceptions\TransportNotFoundException;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\AsyncTransportResolverInterface;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\RPCAsyncTransportFactory;

class RPCAsyncTransportFactoryTest extends TestCase
{
    public function testSelectsResolverBySchemeAndCachesByDsn(): void
    {
        $amqp = $this->createMock(AsyncTransportResolverInterface::class);
        $amqp->expects(self::exactly(2))->method('getSupportSchemes')->willReturn(['amqp', 'amqps']);
        $kafka = $this->createMock(AsyncTransportResolverInterface::class);
        $kafka->expects(self::once())->method('getSupportSchemes')->willReturn(['kafka']);
        $factory = new RPCAsyncTransportFactory(new ArrayIterator([$amqp, $kafka]));

        self::assertSame($kafka, $factory->getTransportResolver('kafka://broker/events'));
        self::assertSame($kafka, $factory->getTransportResolver('kafka://broker/events'));
        self::assertSame($amqp, $factory->getTransportResolver('amqps://broker/tasks'));
    }

    public function testFirstMatchingResolverWins(): void
    {
        $first = $this->createMock(AsyncTransportResolverInterface::class);
        $first->method('getSupportSchemes')->willReturn(['amqp']);
        $second = $this->createMock(AsyncTransportResolverInterface::class);
        $second->expects(self::never())->method('getSupportSchemes');

        self::assertSame($first, (new RPCAsyncTransportFactory([$first, $second]))->getTransportResolver('amqp://broker'));
    }

    public static function unsupportedDsns(): iterable
    {
        yield 'unknown scheme' => ['redis://broker/messages'];
        yield 'missing scheme' => ['/messages'];
        yield 'empty DSN' => [''];
    }

    #[DataProvider('unsupportedDsns')]
    public function testUnsupportedDsnExplainsHowToRegisterResolver(string $dsn): void
    {
        $resolver = $this->createMock(AsyncTransportResolverInterface::class);
        $resolver->method('getSupportSchemes')->willReturn(['amqp']);
        $this->expectException(TransportNotFoundException::class);
        $this->expectExceptionMessage(AsyncTransportResolverInterface::class);
        (new RPCAsyncTransportFactory([$resolver]))->getTransportResolver($dsn);
    }

    public function testEmptyRegistryThrows(): void
    {
        $this->expectException(TransportNotFoundException::class);
        (new RPCAsyncTransportFactory([]))->getTransportResolver('amqp://broker');
    }
}
