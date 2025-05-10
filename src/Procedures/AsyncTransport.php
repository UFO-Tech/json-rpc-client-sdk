<?php

namespace Ufo\RpcSdk\Procedures;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsyncTransport
{
    const string PLACEHOLDER = '{rpc_async_secret}';
    public function __construct(public string $dsn) {}
}
