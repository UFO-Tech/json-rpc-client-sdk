<?php

namespace Ufo\RpcSdk\Procedures;

use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class RequestResponseStack
{
    protected static array $stack = [];
    protected static int $counter = 0;

    public static function addRequest(RpcRequest $request, array $headers = []): void
    {
        static::$stack[static::$counter]['headers'] = $headers;
        static::$stack[static::$counter]['request'] = $request;
    }
    public static function addResponse(RpcResponse $response): void
    {
        static::$stack[static::$counter]['response'] = $response;
        static::$counter++;
    }

    /**
     * @return array
     */
    public static function getAll(): array
    {
        return self::$stack;
    }

    public static function getLastStack(): array
    {
        return self::$stack[static::$counter];
    }

    public static function getLastRequest(): RpcRequest
    {
        return self::$stack[static::$counter]['request'];
    }

    public static function getLastResponse(): RpcResponse
    {
        return self::$stack[static::$counter]['response'];
    }
}
