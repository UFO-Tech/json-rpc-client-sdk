<?php

namespace Ufo\RpcSdk\Procedures\ResponseTransformer;

use Ufo\RpcSdk\Procedures\ResponseTransformer\Interfaces\IResponseHandler;

use function is_array;
use function iterator_to_array;
use function usort;

final class ResponseHandlerQueue
{
    /**
     * @param iterable<IResponseHandler> $handlers
     *
     * @return IResponseHandler[] від найконкретнішого до найзагальнішого
     */
    public static function sort(iterable $handlers): array
    {
        $sorted = is_array($handlers) ? $handlers : iterator_to_array($handlers);
        usort($sorted, static fn (IResponseHandler $a, IResponseHandler $b): int => $b::PRIORITY <=> $a::PRIORITY);
        return $sorted;
    }
}
