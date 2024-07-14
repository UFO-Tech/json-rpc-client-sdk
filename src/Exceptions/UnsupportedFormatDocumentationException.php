<?php

namespace Ufo\RpcSdk\Exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

use function implode;

class UnsupportedFormatDocumentationException extends \Exception
{
    const array SUPPORTED = [
        "1.3.2",
        "1.3.1",
        "1.3.0",
        "1.2.6",
    ];

    protected const string MSG = 'Unsupported documentation format. Supported OpenRpc only versions: ';

    #[Pure]
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->message = (!empty($message)) ? $message : static::MSG . implode(', ', static::SUPPORTED);
    }



}