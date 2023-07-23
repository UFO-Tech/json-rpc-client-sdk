<?php

use Ufo\RpcSdk\Client\Nginx\UserProcedure;
use Ufo\RpcSdk\Procedures\RequestResponseStack;

require_once __DIR__ . '/../vendor/autoload.php';

$headers = [
    'Ufo-RPC-Token'=>'ClientTokenExample'
];


try{
    $userService = new UserProcedure(
        headers: $headers
    );
    $user = $userService->allUsers();
    var_dump($user);

} catch (\Throwable $e) {
    $req = RequestResponseStack::getAll();
    echo $e->getMessage() . PHP_EOL;
}


