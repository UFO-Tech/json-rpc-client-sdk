<?php

namespace Ufo\RpcSdk\Maker\DocReader;

use Symfony\Component\HttpClient\HttpClient;
use Throwable;
use Ufo\RpcSdk\Exceptions\ApiDocReadErrorException;
use Ufo\RpcSdk\Maker\DocReader\Interfaces\IDocReader;

use function json_decode;

readonly class HttpReader implements IDocReader
{
    public function __construct(
        public string $apiUrl,
        protected array $headers = [],
    ) {}

    public function getApiDocumentation(): array
    {
        try {
            $client = HttpClient::create();
            $request = $client->request(
                'GET',
                $this->apiUrl,
                [
                    'headers' => $this->headers,
                ]
            );

            return json_decode($request->getContent(), true);
        } catch (Throwable $e) {
            throw new ApiDocReadErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }
}