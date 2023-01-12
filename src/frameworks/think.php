<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Adapterman\Adapterman;
use think\Cache;
use think\Config;
use think\Console;
use think\Cookie;
use think\Db;
use think\Env;
use think\Event;
use think\event\RouteLoaded;
use think\Lang;
use think\Log;
use think\Middleware;
use think\Request;
use think\Response;
use think\Route;
use think\Session;
use think\Validate;
use think\View;
use Workerman\Worker;

Adapterman::init();

$http_worker = new Worker('http://0.0.0.0:8080');
$http_worker->count = 8;
$http_worker->name = 'AdapterMan';

$http_worker->onMessage = static function ($connection, $request) {
    $connection->send(run());
};

class Http extends think\Http
{
    protected function loadRoutes(): void
    {
        $routePath = $this->getRoutePath();

        if (is_dir($routePath)) {
            $files = glob($routePath . '*.php');
            foreach ($files as $file) {
                // Change include to include_once
                include_once $file;
            }
        }

        $this->app->event->trigger(RouteLoaded::class);
    }
}

class App extends think\App
{
    protected $bind = [
        'app'                     => \think\App::class,
        'cache'                   => Cache::class,
        'config'                  => Config::class,
        'console'                 => Console::class,
        'cookie'                  => Cookie::class,
        'db'                      => Db::class,
        'env'                     => Env::class,
        'event'                   => Event::class,
        'http'                    => Http::class, // Change think\Http to Http
        'lang'                    => Lang::class,
        'log'                     => Log::class,
        'middleware'              => Middleware::class,
        'request'                 => Request::class,
        'response'                => Response::class,
        'route'                   => Route::class,
        'session'                 => Session::class,
        'validate'                => Validate::class,
        'view'                    => View::class,
        'think\DbManager'         => Db::class,
        'think\LogManager'        => Log::class,
        'think\CacheManager'      => Cache::class,
        'Psr\Log\LoggerInterface' => Log::class,
    ];
}

function run()
{
    static $app;
    ob_start();
    $app = $app ?: new App();
    $http = $app->http;
    $tpResponse = $http->run();
    $tpResponse->send();
    $http->end($tpResponse);
    return ob_get_clean();
}

Worker::runAll();
