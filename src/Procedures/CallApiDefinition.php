<?php

namespace Ufo\RpcSdk\Procedures;

use ReflectionClass;
use ReflectionMethod;
use Ufo\RpcObject\RpcRequest;

readonly class CallApiDefinition
{
    public function __construct(
        public ReflectionClass $refClass,
        public ReflectionMethod $refMethod,
        public ApiMethod $method,
        public RpcRequest $rpcRequest
    ) {}

}