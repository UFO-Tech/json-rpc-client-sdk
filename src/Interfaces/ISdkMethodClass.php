<?php

namespace Ufo\RpcSdk\Interfaces;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(ISdkMethodClass::TAG)]
interface ISdkMethodClass
{
    const string TAG = 'ufo.sdk_method_class';
    const string ASYNC_TAG = 'ufo.async_sdk_method_class';
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
