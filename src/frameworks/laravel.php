<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Adapterman\Adapterman;
use Workerman\Worker;

Adapterman::init();

$http_worker                = new Worker('http://0.0.0.0:8080');
$http_worker->count         = 8;
$http_worker->name          = 'AdapterMan';

$http_worker->onMessage = static function ($connection, $request) {
    $connection->send(run());
};

$app = require_once __DIR__.'/../../../../../bootstrap/app.php';

global $kernel;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);


function run()
{
    global $kernel;

    ob_start();

    $response = $kernel->handle(
        $request = Illuminate\Http\Request::capture()
    );

    $response->send();

    $kernel->terminate($request, $response);

    return ob_get_clean();
}

Worker::runAll();
