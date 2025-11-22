# json-rpc-client-sdk
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

Simple clientSDK builder for any json-RPC servers

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) 
![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-client-sdk?label=Size%20of%20the%20repository) 
![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-client-sdk?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) 
![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-client-sdk?color=green&logo=github&style=flat)

# See the [Documentations](https://docs.ufo-tech.space/bin/view/docs/JsonRpcClientSdk/?language=en)

# New in version 4.1
### 🔍 Filtering methods during SDK generation

The SDK supports skipping RPC methods during generation.  
This is done using the `ignoredMethods` option, which accepts a list of masks.

**📌 Mask Rules**
- `*` — any sequence of characters
- `!` at the beginning — **inversion (always generate)**
- `&` at the beginning or after `!` — indicates a sync request
- `~` at the beginning or after `!` — indicates an async request
- Other characters are **literals**, meaning they represent themselves

**✔️ Example masks**

| Mask               | Description                                                                      |
|--------------------|----------------------------------------------------------------------------------|
| `AdminApi.*`       | ignores all methods of `AdminApi` class                                          |
| `Command.run`      | ignores only `Command.run`                                                       |
| `#Command.run`     | ignores only `Command.run` in sync API                                           |
| `*.delete`         | ignores all `delete()` methods in any class                                      |
| `!~Comment.delete` | **always generates `Comment.delete` for async API even if `*.delete` blocks it** |
| `*.*Test`          | ignores all methods ending with `Test`                                           |

**🚫 Prohibited**

| Mask            | Reason                                   |
|-----------------|------------------------------------------|
| `User.?pdate`   | `?` is not supported                     |
| `~&User.update` | `~` and `&` in one mask is not supported |
| `[A-Z]*.create` | regex is not supported                   |

**📎 Usage Example**

```php
$configHolder = new ConfigsHolder(
    docReader: new HttpReader($apiUrl),
    projectRootDir: getcwd(),
    apiVendorAlias: $vendorName,
    ignoredMethods: [
        'AdminApi.*',
        'Command.run',
        '*.delete',
        '!Comment.delete',
        '*.*Test'
    ]
);
```

Masks allow you to easily remove service procedures, test methods, and unwanted CRUD operations from SDK generation.

## Generate SDK
Run cli command ``` php bin/make.php ```
``` bash
$ php bin/make.php http://some.url/api
  > Enter API vendor name: some_vendor
  > Enter methods to ignore (comma-separated) or empty: *.delete,!Comment.delete
```
Or 
``` bash
$ php bin/make.php 
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