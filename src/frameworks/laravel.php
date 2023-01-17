<?php

use Adapterman\Adapterman;
use App\Exceptions\Handler;
use App\Http\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Tymon\JWTAuth\JWT;
use Workerman\Worker;

$webrootDir = __DIR__ . '/../../../../../';
require_once $webrootDir . 'vendor/autoload.php';

Adapterman::init();

$port = trim(shell_exec("cat " . $webrootDir . ".env | grep HTTP_PORT | cut -d '=' -f2") ?: '8080');
$host = trim(shell_exec("cat " . $webrootDir . ".env | grep HTTP_HOST | cut -d '=' -f2") ?: '0.0.0.0');

$http_worker                = new Worker('http://' . $host . ':' . $port);
$http_worker->count         = 8;
$http_worker->name          = 'AdapterMan';

$http_worker->onMessage = static function ($connection, $request) {
    $connection->send(run($request));
};

if (class_exists(JWT::class)) {
    JWT::customTokenParser(function () {
        return request()->bearerToken();
    });
}

global $mime, $hasJwt;
$mime = new MimeTypes();


function run($worker_req)
{
    global $hasJwt;

    $app = new Application(
        $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__ . '/../../../../../bootstrap/')
    );

    $app->singleton(
        HttpKernel::class,
        Kernel::class
    );

    $app->singleton(
        ExceptionHandler::class,
        Handler::class
    );

    Request::setFactory(
        fn () => new Request(
            $worker_req['get'],
            $worker_req['post'],
            $worker_req['request'],
            $worker_req['cookie'],
            $worker_req['files'],
            $worker_req['server'],
            (isset($worker_req['server']['CONTENT_TYPE']) && Str::contains($worker_req['server']['CONTENT_TYPE'], 'application/json')) ? json_encode($worker_req['post']) : null
        )
    );

    $request = Request::capture();

    if ($request->method() === 'GET') {
        $parse = parse_url($request->getRequestUri());
        if ($parse && isset($parse['path']) && Str::contains($parse['path'], '.')) {
            global $mime;
            if ($parse && isset($parse['path'])) {
                $filepath = public_path($parse['path']);
                if (is_file($filepath) && !Str::endsWith(Str::lower($parse['path']), ['.php', '.phtml', '.php5', '.htaccess', '.db', '.sqlite', '.sqlite3', '.sql',])) {
                    $ext = strtolower(Arr::last(explode('.', $parse['path'])));
                    header('Content-Type: ' . Arr::first($mime->getMimeTypes($ext), null, 'text/plain'));
                    return file_get_contents($filepath);
                }
            }
        }
    }

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    ob_start();

    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);

    $output = ob_get_clean();

    $app->get('db.connection')->disconnect();

    unset($request, $response, $app, $kernel);

    return $output;
}

Worker::runAll();
