<?php

namespace Ufo\RpcSdk\Procedures;

use Attribute;

#[Attribute]
class ApiMethod
{

    public function __construct(protected string $method)
    {
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

}
