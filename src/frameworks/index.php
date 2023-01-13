<?php

$baseDir = realpath(__DIR__ . '/../../../../../');
if (is_file("$baseDir/artisan")) {
    if (class_exists(Illuminate\Contracts\Http\Kernel::class)) {
        include __DIR__ . '/laravel.php';
    } else {
        include __DIR__ . '/lumen.php';
    }
    return;
}

if (is_file("$baseDir/think")) {
    include __DIR__ . '/think.php';
    return;
}
