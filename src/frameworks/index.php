<?php
$baseDir = realpath(__DIR__ . '/../../../../../');
if (is_file("$baseDir/artisan")) {
    include __DIR__ . '/laravel.php';
    return;
}

if (is_file("$baseDir/think")) {
    include __DIR__ . '/think.php';
    return;
}
