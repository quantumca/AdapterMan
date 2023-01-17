<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Adapterman\Adapterman;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tymon\JWTAuth\JWT;
use Workerman\Worker;

Adapterman::init();

$port = trim(shell_exec("cat " . __DIR__ . '/../../../../../' . ".env | grep HTTP_PORT | cut -d '=' -f2") ?: '8080');
$host = trim(shell_exec("cat " . __DIR__ . '/../../../../../' . ".env | grep HTTP_HOST | cut -d '=' -f2") ?: '0.0.0.0');

$http_worker                = new Worker('http://' . $host . ':' . $port);
$http_worker->count         = 8;
$http_worker->name          = 'AdapterMan';

$http_worker->onMessage = static function ($connection, $request) {
    $connection->send(run($request));
};

function tryFiles($path)
{
    $parse = parse_url($path);
    $result = [];
    if ($parse && isset($parse['path'])) {
        $filepath = __DIR__ . '/../../../../../public' . $parse['path'];
        if (is_file($filepath) && Str::endsWith(Str::lower($parse['path']), [
            '.css', '.js',
            '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico',
            '.woff', '.woff2', '.ttf', '.eot', '.otf',
            '.map',
            '.txt', '.xml', '.json',
            '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx',
            '.zip', '.rar', '.7z', '.gz', '.tar', '.bz2',
        ]) ) {
            $result = [
                'content' => file_get_contents($filepath)
            ];

            $ext = Arr::last(explode('.', $parse['path']));

            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'application/font-woff',
                'woff2' => 'application/font-woff2',
                'ttf' => 'application/x-font-ttf',
                'eot' => 'application/vnd.ms-fontobject',
                'json' => 'application/json',
                'map' => 'application/json',
                'txt' => 'text/plain',
                'xml' => 'application/xml',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                '7z' => 'application/x-7z-compressed',
                'gz' => 'application/gzip',
                'tar' => 'application/x-tar',
                'bz2' => 'application/x-bzip2',
            ];
            $result['content-type'] = $mimeTypes[$ext] ?? 'text/plain';

            return $result;
        }
    }

    return false;
}

function run($workermanRequest)
{
    $app = new Illuminate\Foundation\Application(
        $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__ . '/../../../../../bootstrap/')
    );

    $app->singleton(
        Illuminate\Contracts\Http\Kernel::class,
        App\Http\Kernel::class
    );

    $app->singleton(
        Illuminate\Contracts\Console\Kernel::class,
        App\Console\Kernel::class
    );

    $app->singleton(
        Illuminate\Contracts\Debug\ExceptionHandler::class,
        App\Exceptions\Handler::class
    );

    Illuminate\Http\Request::setFactory(
        fn () => new Illuminate\Http\Request(
            $workermanRequest['get'],
            $workermanRequest['post'],
            $workermanRequest['request'],
            $workermanRequest['cookie'],
            $workermanRequest['files'],
            $workermanRequest['server'],
            (isset($workermanRequest['server']['CONTENT_TYPE']) && Str::contains($workermanRequest['server']['CONTENT_TYPE'], 'application/json')) ? json_encode($workermanRequest['post']) : null
        )
    );

    $request = Illuminate\Http\Request::capture();

    if ($request->method() === 'GET') {
        $parse = parse_url($request->getRequestUri());
        if ($parse && isset($parse['path'])) {
            if (Str::endsWith(Str::lower($parse['path']), [
                '.css', '.js',
                '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico',
                '.woff', '.woff2', '.ttf', '.eot', '.otf',
                '.map',
                '.txt', '.xml', '.json',
                '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx',
                '.zip', '.rar', '.7z', '.gz', '.tar', '.bz2',
            ]) ) {
                $result = tryFiles($parse['path']);
                if ($result) {
                    if (isset($result['content-type'])) {
                        header('Content-Type: ' . $result['content-type']);
                    }
                    if (isset($result['content'])) {
                        return $result['content'];
                    }
                }
            }
        }
    }

    if (class_exists(JWT::class)) {
        JWT::customTokenParser(fn () => $request->bearerToken());
    }

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    ob_start();

    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);

    unset($request, $response);

    return ob_get_clean();
}
