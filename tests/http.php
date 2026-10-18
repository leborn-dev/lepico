<?php
/** Verify actual HTTP status codes and response bodies with PHP's local server. */
$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($socket === false) {
    throw new RuntimeException($errorMessage, $errorCode);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = tempnam(sys_get_temp_dir(), 'lepico-http-');
$server = proc_open(
    array(PHP_BINARY, '-S', $address, '-t', dirname(__DIR__), __DIR__ . '/router.php'),
    array(0 => array('pipe', 'r'), 1 => array('file', $log, 'a'), 2 => array('file', $log, 'a')),
    $pipes,
    dirname(__DIR__)
);
if (!is_resource($server)) {
    throw new RuntimeException('Could not start local HTTP server');
}
fclose($pipes[0]);
try {
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if ($connection !== false) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(20000);
    }
    if (!$ready) {
        throw new RuntimeException('HTTP server did not start: ' . file_get_contents($log));
    }
    foreach (array(
        array('/', 200, '<h2>Welcome to Pico</h2>'),
        array('/?sub/page', 200, '<title>Sub Page | Pico</title>'),
        array('/?missing', 404, '<title>Error 404 | Pico</title>'),
        array('/fixture', 200, '<title>Home &lt;unsafe&gt; | Fixture &amp; site</title>'),
        array('/fixture?missing', 404, '<h1>Page not found</h1>'),
        array('/fixture?quoted-time', 200, '<time>10/18/26 00:00:00</time>'),
        array('/legacy-date?quoted-time', 200, '<time></time>'),
        array('/legacy-date?era-date', 200, '<time>令和8年10月18日</time>'),
    ) as $case) {
        list($path, $status, $expected) = $case;
        $context = stream_context_create(array('http' => array('ignore_errors' => true, 'timeout' => 5)));
        $response = fopen('http://' . $address . $path, 'r', false, $context);
        if ($response === false) {
            throw new RuntimeException('HTTP request failed: ' . $path);
        }
        $body = stream_get_contents($response);
        $headers = stream_get_meta_data($response)['wrapper_data'];
        fclose($response);
        if (strpos($headers[0], ' ' . $status . ' ') === false || strpos($body, $expected) === false) {
            throw new RuntimeException('Unexpected response for ' . $path . ': ' . implode("\n", $headers) . "\n" . $body);
        }
        if (preg_match('/(?:Fatal error|Warning:|Deprecated:|Cannot find .vendor\/autoload)/', $body)) {
            throw new RuntimeException('PHP error leaked in HTTP response: ' . $path);
        }
        echo 'PASS HTTP ' . $status . ' ' . $path . "\n";
    }
} finally {
    proc_terminate($server);
    proc_close($server);
    unlink($log);
}
