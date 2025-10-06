<?php

namespace Ufo\RpcSdk\Maker\Definitions;

class UfoEnvelope
{
    public function __construct(protected int $version) {}

    public function getVersion(): int
    {
        return $this->version;
    }
}
