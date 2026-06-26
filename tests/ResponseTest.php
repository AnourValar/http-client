<?php

namespace AnourValar\HttpClient\Tests;

use AnourValar\HttpClient\Response;

class ResponseTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_basic_accessors()
    {
        $response = new Response(
            "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Foo: bar\r\n",
            '{"key":"value"}',
            ['http_code' => 200, 'total_time' => 0.25]
        );

        $this->assertSame(200, $response->status());
        $this->assertTrue($response->success());
        $this->assertSame('{"key":"value"}', $response->body());
        $this->assertSame('{"key":"value"}', (string) $response);
        $this->assertSame(['key' => 'value'], $response->json());
        $this->assertSame(250, $response->durationMs());
        $this->assertSame(['http_code' => 200, 'total_time' => 0.25], $response->curlGetInfo());
    }

    /**
     * @return void
     */
    public function test_headers_parsing()
    {
        $response = new Response(
            "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nX-Foo: bar\r\n",
            '',
            ['http_code' => 200, 'total_time' => 0.1]
        );

        $headers = $response->headers();
        $this->assertSame('text/html', $headers['Content-Type']);
        $this->assertSame('bar', $headers['X-Foo']);
        // The status-line (no colon) is kept under a numeric key
        $this->assertContains('HTTP/1.1 200 OK', $headers);
    }

    /**
     * @return void
     */
    public function test_header_lookup_is_case_and_space_insensitive()
    {
        $response = new Response("Content-Type: application/json\r\n", '', ['http_code' => 200, 'total_time' => 0.1]);

        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame('application/json', $response->header('content-type'));
        $this->assertSame('application/json', $response->header('CONTENT - TYPE'));
        $this->assertNull($response->header('X-Missing'));
    }

    /**
     * @return void
     */
    public function test_repeated_headers_become_an_array()
    {
        $response = new Response("Set-Cookie: a=1\nSet-Cookie: b=2\n", '', ['http_code' => 200, 'total_time' => 0.1]);

        $this->assertSame(['a=1', 'b=2'], $response->headers()['Set-Cookie']);
    }

    /**
     * @return void
     */
    public function test_empty_headers()
    {
        $response = new Response(null, null, ['http_code' => 200, 'total_time' => 0.1]);

        $this->assertSame([], $response->headers());
        $this->assertNull($response->body());
        $this->assertSame('', (string) $response);
    }

    /**
     * @return void
     */
    public function test_status_boundaries_for_success()
    {
        foreach ([200, 201, 204, 299] as $code) {
            $this->assertTrue($this->statusResponse($code)->success(), "Status $code should be a success");
        }

        foreach ([100, 199, 300, 301, 400, 404, 500] as $code) {
            $this->assertFalse($this->statusResponse($code)->success(), "Status $code should not be a success");
        }
    }

    /**
     * @return void
     */
    public function test_success_is_false_without_status()
    {
        $response = new Response(null, '{}', []);

        $this->assertNull($response->status());
        $this->assertFalse($response->success());
    }

    /**
     * @return void
     */
    public function test_success_with_success_key()
    {
        $this->assertTrue($this->jsonResponse(['ok' => true])->success('ok'));
        $this->assertTrue($this->jsonResponse(['ok' => 1])->success('ok'));
        $this->assertTrue($this->jsonResponse(['ok' => 'yes'])->success('ok'));
        $this->assertTrue($this->jsonResponse(['ok' => [1, 2]])->success('ok'));
        // An (empty) array is still considered a present value
        $this->assertTrue($this->jsonResponse(['ok' => []])->success('ok'));

        $this->assertFalse($this->jsonResponse(['ok' => false])->success('ok'));
        $this->assertFalse($this->jsonResponse(['ok' => 0])->success('ok'));
        $this->assertFalse($this->jsonResponse(['ok' => null])->success('ok'));
        $this->assertFalse($this->jsonResponse(['ok' => ''])->success('ok'));
        $this->assertFalse($this->jsonResponse(['other' => true])->success('ok'));
    }

    /**
     * @return void
     */
    public function test_array_access()
    {
        $response = new Response(null, '{"key":"value","list":[1,2]}', ['http_code' => 200, 'total_time' => 0.1]);

        $this->assertSame('value', $response['key']);
        $this->assertSame([1, 2], $response['list']);
        $this->assertNull($response['missing']);

        $this->assertTrue(isset($response['key']));
        $this->assertFalse(isset($response['missing']));

        $response['added'] = 'new';
        $this->assertSame('new', $response['added']);

        $response[] = 'appended';
        $this->assertContains('appended', $response->json());

        unset($response['key']);
        $this->assertFalse(isset($response['key']));
    }

    /**
     * @return void
     */
    public function test_dump_on_success()
    {
        $response = new Response(
            "Content-Type: application/json\r\n",
            '{"key":"value"}',
            ['http_code' => 200, 'total_time' => 0.1]
        );

        $dump = $response->dump();

        // On success (and without $all) the technical info is omitted
        $this->assertArrayNotHasKey('curl_getinfo', $dump);
        $this->assertArrayNotHasKey('response_headers', $dump);
        $this->assertSame(['key' => 'value'], $dump['response_body']);
    }

    /**
     * @return void
     */
    public function test_dump_on_failure_includes_debug_info()
    {
        $response = new Response(
            "Content-Type: application/json\r\n",
            '{"error":"oops"}',
            ['http_code' => 500, 'total_time' => 0.1]
        );

        $dump = $response->dump();

        $this->assertArrayHasKey('curl_getinfo', $dump);
        $this->assertArrayHasKey('response_headers', $dump);
        $this->assertSame(['error' => 'oops'], $dump['response_body']);
    }

    /**
     * @return void
     */
    public function test_dump_all_includes_debug_info_on_success()
    {
        $response = new Response(null, '{"key":"value"}', ['http_code' => 200, 'total_time' => 0.1]);

        $dump = $response->dump(true);

        $this->assertArrayHasKey('curl_getinfo', $dump);
        $this->assertArrayHasKey('response_headers', $dump);
    }

    /**
     * @return void
     */
    public function test_dump_masks_authorization_header()
    {
        $response = new Response(
            null,
            'body',
            [
                'http_code' => 500,
                'total_time' => 0.1,
                'request_header' => "GET / HTTP/1.1\r\nAuthorization: Bearer super-secret\r\nAccept: */*\r\n",
            ]
        );

        $protected = $response->dump();
        $this->assertStringNotContainsString('super-secret', $protected['curl_getinfo']['request_header']);
        // Each token is masked in place: first/last quarter kept, the middle replaced with asterisks
        $this->assertStringContainsString('Authorization: B****r s***r-s****t', $protected['curl_getinfo']['request_header']);
        // The non-sensitive headers are left untouched
        $this->assertStringContainsString('Accept: */*', $protected['curl_getinfo']['request_header']);

        $raw = $response->dump(false, false);
        $this->assertStringContainsString('super-secret', $raw['curl_getinfo']['request_header']);
    }

    /**
     * @return void
     */
    public function test_dump_masks_long_body_parameters()
    {
        // A long token-like value (>= 30 chars): keep first/last 10, mask the rest
        $token = 'abcdefghij' . str_repeat('X', 20) . 'klmnopqrst'; // exactly 40 chars

        $response = new Response(
            null,
            'body',
            ['http_code' => 500, 'total_time' => 0.1, 'request_body' => $token]
        );

        $masked = $response->dump()['curl_getinfo']['request_body'];
        $this->assertStringNotContainsString($token, $masked);
        $this->assertSame('abcdefghij' . str_repeat('*', 20) . 'klmnopqrst', $masked);

        // Short, non-sensitive values are left untouched
        $shortResponse = new Response(
            null,
            'body',
            ['http_code' => 500, 'total_time' => 0.1, 'request_body' => 'foo=bar&baz=qux']
        );
        $this->assertSame('foo=bar&baz=qux', $shortResponse->dump()['curl_getinfo']['request_body']);

        // protectSensitive disabled => raw body
        $this->assertSame($token, $response->dump(false, false)['curl_getinfo']['request_body']);
    }

    /**
     * @return void
     */
    public function test_dump_converts_cp1251_body()
    {
        $body = mb_convert_encoding('Привет', 'cp1251', 'utf-8');

        $response = new Response(
            null,
            $body,
            ['http_code' => 200, 'total_time' => 0.1, 'content_type' => 'text/html; charset=cp1251']
        );

        $this->assertSame('Привет', $response->dump()['response_body']);
    }

    /**
     * @param int $code
     * @return \AnourValar\HttpClient\Response
     */
    private function statusResponse(int $code): Response
    {
        return new Response(null, '{}', ['http_code' => $code, 'total_time' => 0.1]);
    }

    /**
     * @param array $body
     * @return \AnourValar\HttpClient\Response
     */
    private function jsonResponse(array $body): Response
    {
        return new Response(null, json_encode($body), ['http_code' => 200, 'total_time' => 0.1]);
    }
}
