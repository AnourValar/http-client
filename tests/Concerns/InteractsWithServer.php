<?php

namespace AnourValar\HttpClient\Tests\Concerns;

/**
 * Boots PHP's built-in web server (tests/server.php) once per test-class so the
 * HTTP client can be exercised against a real, local and deterministic endpoint.
 */
trait InteractsWithServer
{
    /**
     * @var resource|null
     */
    protected static $serverProcess = null;

    /**
     * @var string
     */
    protected static string $serverUrl;

    /**
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $port = static::findFreePort();
        static::$serverUrl = "http://127.0.0.1:{$port}";

        $router = realpath(__DIR__ . '/../server.php');
        $command = sprintf(
            'exec %s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($router)
        );

        static::$serverProcess = proc_open(
            $command,
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes
        );

        // Wait until the server starts accepting connections
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($connection) {
                fclose($connection);
                return;
            }

            usleep(50000);
        }

        throw new \RuntimeException('The test HTTP server did not start in time.');
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        if (is_resource(static::$serverProcess)) {
            proc_terminate(static::$serverProcess);
            proc_close(static::$serverProcess);
            static::$serverProcess = null;
        }

        parent::tearDownAfterClass();
    }

    /**
     * Build an absolute url against the test server.
     *
     * @param string $path
     * @return string
     */
    protected function url(string $path = ''): string
    {
        return static::$serverUrl . $path;
    }

    /**
     * @return int
     */
    protected static function findFreePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($server, false);
        fclose($server);

        return (int) mb_substr($name, mb_strrpos($name, ':') + 1);
    }
}
