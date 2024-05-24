<?php

namespace Ufo\RpcSdk\Procedures;

use ReflectionClass;
use ReflectionMethod;

readonly class CallApiDefinition
{

    public function __construct(
        public ReflectionClass $refClass,
        public ReflectionMethod $refMethod,
        public ApiMethod $method,
        public array $body
    ) {}

}