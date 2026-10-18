<?php
/** Router for the loopback-only HTTP regression server. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__) . '/vendor/autoload.php';
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($requestPath, array('/fixture', '/legacy-date'), true)) {
    $pico = new Pico(__DIR__ . '/fixtures/site', 'config/', 'plugins/', 'themes/');
    if ($requestPath === '/legacy-date') {
        $pico->setConfig(array('date_format' => '%Od %B %EY'));
    }
    echo $pico->run();
} else {
    require dirname(__DIR__) . '/index.php';
}
