<?php

namespace Ufo\RpcSdk\Interfaces;

interface ISdkMethodClass
{
    /**
     * @return int|string
     */
    public function getRequestId(): int|string;

    /**
     * @param int|string $requestId
     * @return $this
     */
    public function setRequestId(int|string $requestId): static;

    /**
     * @return string
     */
    public function getRpcVersion(): string;

    /**
     * @param string $version
     * @return $this
     */
    public function setRpcVersion(string $version): static;
}
