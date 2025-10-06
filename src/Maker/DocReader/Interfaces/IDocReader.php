<?php

namespace Ufo\RpcSdk\Maker\DocReader\Interfaces;

use Ufo\RpcSdk\Exceptions\ApiDocReadErrorException;

interface IDocReader
{
    /**
     * @throws ApiDocReadErrorException
     */
    public function getApiDocumentation(): array;
}