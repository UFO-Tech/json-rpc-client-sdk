<?php

namespace Ufo\RpcSdk\Procedures;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ApiUrl
{

    public function __construct(
        protected string $url,
        protected string $method = 'POST'
    )
    {
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getApiHost(): string
    {
        return parse_url($this->url)["host"];
    }
}
