<?php
defined('ABSPATH') || exit;
spl_autoload_register(static function ($class) {
    $prefixes = array(
        'TMSVendor\\Phone\\' => __DIR__ . '/phone/src/',
        'TMSVendor\\Mbstring\\' => __DIR__ . '/mbstring/',
    );
    foreach ($prefixes as $prefix => $directory) {
        if (strpos($class, $prefix) !== 0) { continue; }
        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
require_once __DIR__ . '/mbstring/bootstrap.php';
