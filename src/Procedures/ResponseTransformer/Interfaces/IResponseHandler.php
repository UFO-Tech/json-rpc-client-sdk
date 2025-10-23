<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\RpcSdk\Procedures\ResponseTransformer\Exceptions\SdkResponseHandlerException;
use Ufo\RpcSdk\Procedures\ResponseTransformer\SdkResponseCreator;

#[AutoconfigureTag(IResponseHandler::TAG)]
interface IResponseHandler
{
    const string TAG = 'ufo.sdk_response_handler';

    /**
     * @param array $schema
     * @param mixed $result
     * @param callable $transform
     * @param SdkResponseCreator $creator
     * @throws SdkResponseHandlerException
     * @return mixed
     */
    public function handle(array $schema, mixed $result, callable $transform, SdkResponseCreator $creator): mixed;

    public function canHandle(array $schema): bool;

}