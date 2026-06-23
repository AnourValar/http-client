<?php

namespace AnourValar\HttpClient\Tests;

use AnourValar\HttpClient\FakeResponse;

class FakeResponseTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_array_body_is_encoded_to_json()
    {
        $response = new FakeResponse(['Content-Type' => 'application/json'], ['foo' => 'bar', 'list' => [1, 2]]);

        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame(['foo' => 'bar', 'list' => [1, 2]], $response->json());
        $this->assertSame('{"foo":"bar","list":[1,2]}', $response->body());
    }

    /**
     * @return void
     */
    public function test_default_status_is_200()
    {
        $response = new FakeResponse([], ['ok' => true]);

        $this->assertSame(200, $response->status());
        $this->assertTrue($response->success());
        $this->assertSame(100, $response->durationMs());
    }

    /**
     * @return void
     */
    public function test_scalar_status_argument_sets_http_code()
    {
        $response = new FakeResponse([], 'Not Found', 404);

        $this->assertSame(404, $response->status());
        $this->assertFalse($response->success());
        $this->assertSame('Not Found', $response->body());
        $this->assertNull($response->json());
    }

    /**
     * @return void
     */
    public function test_array_curl_get_info_argument()
    {
        $response = new FakeResponse([], 'x', ['http_code' => 201, 'total_time' => 2.0]);

        $this->assertSame(201, $response->status());
        $this->assertTrue($response->success());
        $this->assertSame(2000, $response->durationMs());
    }

    /**
     * @return void
     */
    public function test_numeric_and_assoc_headers()
    {
        $response = new FakeResponse(['HTTP/1.1 200 OK', 'X-Foo' => 'bar'], 'body');

        $this->assertSame('bar', $response->header('X-Foo'));
        $this->assertContains('HTTP/1.1 200 OK', $response->headers());
    }

    /**
     * @return void
     */
    public function test_empty_headers()
    {
        $response = new FakeResponse([], 'body');

        $this->assertSame([], $response->headers());
    }
}
