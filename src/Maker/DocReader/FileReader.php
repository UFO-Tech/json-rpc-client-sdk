<?php

namespace Ufo\RpcSdk\Maker\DocReader;

use Ufo\RpcSdk\Exceptions\ApiDocReadErrorException;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;

use function file_exists;
use function file_get_contents;
use function json_decode;

readonly class FileReader implements IDocReader
{
    public function __construct(
        public string $docFilePath,
    ) {}

    public function getApiDocumentation(): array
    {
        try {
            if (!file_exists($this->docFilePath)) {
                throw new \RuntimeException('Doc file is not found');
            }
            return json_decode(file_get_contents($this->docFilePath), true);
        } catch (\Throwable $e) {
            throw new ApiDocReadErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }
}