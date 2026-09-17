<?php

namespace Ufo\RpcSdk\Tests\Procedures\AsyncTransportResolvers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\AbstractAsyncTransportResolver;
use Ufo\RpcSdk\Procedures\AsyncTransportResolvers\AsyncStampDTO;

class AbstractAsyncTransportResolverTest extends TestCase
{
    public static function serializers(): iterable
    {
        yield 'default serializer' => [false];
        yield 'custom serializer' => [true];
    }

    #[DataProvider('serializers')]
    public function testCachesEachDsnAndPassesOptionsAndSerializer(bool $custom): void
    {
        $serializer = $custom ? $this->createMock(SerializerInterface::class) : null;
        $first = $this->createMock(TransportInterface::class);
        $second = $this->createMock(TransportInterface::class);
        $factory = $this->createMock(TransportFactoryInterface::class);
        $calls = [];
        $factory->expects(self::exactly(2))->method('createTransport')->willReturnCallback(
            function (string $dsn, array $options, SerializerInterface $actualSerializer) use (&$calls, $serializer, $first, $second): TransportInterface {
                self::assertSame(['queue' => parse_url($dsn, PHP_URL_PATH)], $options);
                if ($serializer !== null) {
                    self::assertSame($serializer, $actualSerializer);
                } else {
                    self::assertInstanceOf(PhpSerializer::class, $actualSerializer);
                }
                $calls[] = $dsn;
                return count($calls) === 1 ? $first : $second;
            }
        );
        $resolver = new class($factory, $serializer) extends AbstractAsyncTransportResolver {
            protected function asyncOptions(string $dsn): array
            {
                return ['queue' => parse_url($dsn, PHP_URL_PATH)];
            }

            public function createAsyncStamp(AsyncStampDTO $asyncStampData): StampInterface
            {
                return new DelayStamp(0);
            }

            public function getSupportSchemes(): array
            {
                return ['amqp'];
            }
        };

        self::assertSame($first, $resolver->getTransport('amqp://broker/first'));
        self::assertSame($first, $resolver->getTransport('amqp://broker/first'));
        self::assertSame($second, $resolver->getTransport('amqp://broker/second'));
        self::assertSame($second, $resolver->getTransport('amqp://broker/second'));
        self::assertSame(['amqp://broker/first', 'amqp://broker/second'], $calls);
    }
}
