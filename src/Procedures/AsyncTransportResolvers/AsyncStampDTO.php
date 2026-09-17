<?php

namespace Ufo\RpcSdk\Procedures\AsyncTransportResolvers;

readonly class AsyncStampDTO
{
    public function __construct(
        public string $asyncDSN,
        public bool $highPriority = true,
        public array $extra = [],
    ) {}

}