<?php

declare(strict_types=1);

// Tests use the shipped autoloader (module classes + bundled library) plus dev tooling from tools/.
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../tools/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Gibbon\\Module\\BigBlueButton\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
