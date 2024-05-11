# json-rpc-client-sdk
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

Simple clientSDK builder for any json-RPC servers

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) 
![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-client-sdk?label=Size%20of%20the%20repository) 
![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-client-sdk?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) 
![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-client-sdk?color=green&logo=github&style=flat)

# See the [Documentations](https://docs.ufo-tech.space/bin/view/docs/JsonRpcClientSdk/?language=en)
## Generate SDK 
Run cli command ``` php bin/make.php ```

``` bash
$ php bin/make.php
  > Enter API vendor name: some_vendor
  > Enter the API url: http://some.url/api
```

## Use SDK
This example shows working with the generated SDK.
IMPORTANT: You may have other procedure classes. The example only shows the concept of interaction.
```php
<?php

use Symfony\Component\HttpClient\CurlHttpClient;
use Ufo\RpcSdk\Client\Shortener\UserProcedure;
use Ufo\RpcSdk\Client\Shortener\PingProcedure;
use Ufo\RpcSdk\Procedures\AbstractProcedure;

require_once __DIR__ . '/../vendor/autoload.php';

$headers = [
    'Ufo-RPC-Token'=>'some_security_token'
];

try {
    $pingService = new PingProcedure(
        headers: $headers
    );
    echo $pingService->ping(); // print "PONG"

// ...

    $userService = new UserProcedure(
        headers: $headers,
        requestId: uniqid(), 
        rpcVersion: AbstractProcedure::DEFAULT_RPC_VERSION,
        httpClient: new CurlHttpClient(),
        httpRequestOptions: []
    );
    $user = $userService->createUser(
        login: 'some_login', 
        password: 'some_password'
    );
    var_dump($user);
    // array(3) {
    //  ["id"]=> int(279232969)
    //  ["login"]=> string(3) "some_login"
    //  ["status"]=> int(0)
    
} catch (\Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
}
// ...

```
## Debug request and response

```php
<?php

// ...
use Ufo\RpcSdk\Procedures\RequestResponseStack;
// ...

$fullStack = RequestResponseStack::getAll(); // get all previous requests and responses
$lastStack = RequestResponseStack::getLastStack(); // get last requests and responses

$lastRequest = RequestResponseStack::getLastRequest(); // get last request
$lastResponse = RequestResponseStack::getLastResponse(); // get last response
// ...

```

## Profit