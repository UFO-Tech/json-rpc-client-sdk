<?php

namespace Ufo\RpcSdk\Tests\Procedures\ResponseTransformer;

use ArrayIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcSdk\Procedures\ResponseTransformer\CollectionResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\DtoResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\EnumResponseHandler;
use Ufo\RpcSdk\Procedures\ResponseTransformer\ResponseHandlerQueue;
use Ufo\RpcSdk\Procedures\ResponseTransformer\UnionResponseHandler;

class ResponseHandlerQueueTest extends TestCase
{
    public static function inputTypes(): iterable
    {
        yield 'array' => [false];
        yield 'iterator' => [true];
    }

    #[DataProvider('inputTypes')]
    public function testSpecificHandlersPrecedeGeneralHandlers(bool $iterator): void
    {
        $enum = new EnumResponseHandler();
        $union = new UnionResponseHandler();
        $collection = new CollectionResponseHandler();
        $dto = new DtoResponseHandler();
        $input = ['dto' => $dto, 'collection' => $collection, 'enum' => $enum, 'union' => $union];

        self::assertSame([$enum, $union, $collection, $dto], ResponseHandlerQueue::sort($iterator ? new ArrayIterator($input) : $input));
        self::assertSame(['dto', 'collection', 'enum', 'union'], array_keys($input));
    }

    public function testEmptyQueue(): void
    {
        self::assertSame([], ResponseHandlerQueue::sort([]));
        self::assertSame([], ResponseHandlerQueue::sort(new ArrayIterator()));
    }
}
