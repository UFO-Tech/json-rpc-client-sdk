<?php

use Symfony\Component\HttpClient\CurlHttpClient;
use Ufo\RpcObject\Transformer\ResponseCreator;
use Ufo\RpcSdk\Client\Shortener\UserProcedure;
use Ufo\RpcSdk\Procedures\AbstractProcedure;
use Ufo\RpcSdk\Procedures\RequestResponseStack;

require_once __DIR__ . '/../vendor/autoload.php';

$headers = [
    'Ufo-RPC-Token'=>'some_security_token'
];


try{
    $userService = new UserProcedure(
        headers: $headers
    );
    $user = $userService->createUser('qwe', 'dfdsfdsfsd');
    var_dump($user);

} catch (\Throwable $e) {
    $req = RequestResponseStack::getAll();
    echo $e->getMessage() . PHP_EOL;
}


