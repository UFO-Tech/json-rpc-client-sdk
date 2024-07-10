<?php

namespace Ufo\RpcSdk\Maker\Definitions;

use Countable;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RPC\Assertions;

use function array_keys;
use function count;
use function implode;
use function is_null;
use function str_pad;

use const PHP_EOL;

class AssertionsDefinition extends AttributeDefinition implements Countable
{
    /**
     * @throws WrongWayException
     */
    public function __construct(protected ?string $constructorArgs = null)
    {
        parent::__construct(Assertions::class);
    }

    protected function buildSignature(int $tab = 2): array
    {
        $res = [];
        if (!is_null($this->constructorArgs)) {
            $res =  [
                '['
                 . PHP_EOL
                 . str_pad(' ', $tab * 4)
                 . $this->constructorArgs
                 . PHP_EOL
                 . str_pad(' ', ($tab - 1) * 4)
                 . ']'
            ];
        }
        return $res;
    }

    public function getSignature(int $tab = 2): string
    {
        $res = '';
        if ($this->constructorArgs) {
            $res = parent::getSignature($tab);
        }
        return $res;
    }

    public function count(): int
    {
        return is_null($this->constructorArgs) ? 0 : 1;
    }

}