<?php

namespace Ufo\RpcSdk\Procedures;

use Attribute;

use function sprintf;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsyncTransport
{
    const string PLACEHOLDER = '{%s_secret}';
    public function __construct(public string $dsn) {}

    public static function getSecretPlaceholder(string $transportName): string
    {
        return sprintf(AsyncTransport::PLACEHOLDER, $transportName);
    }
}
