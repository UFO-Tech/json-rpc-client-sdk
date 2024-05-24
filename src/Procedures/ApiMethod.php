<?php

namespace Ufo\RpcSdk\Procedures;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiMethod
{

    public function __construct(public string $method) {}
}
