<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces;

use Ufo\RpcSdk\Procedures\ResponseTransformer\SdkResponseCreator;

interface IResponseHandler
{
    public function handle(array $schema, array $parent, mixed $result, SdkResponseCreator $creator): mixed;

    public function canHandle(array $schema, array $parent): bool;

}