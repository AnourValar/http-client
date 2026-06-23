<?php

namespace AnourValar\HttpClient\Tests;

use AnourValar\HttpClient\Events\HttpRequestComplete;
use AnourValar\HttpClient\Http;
use AnourValar\HttpClient\Tests\Concerns\InteractsWithServer;
use Illuminate\Support\Facades\Event;

class HttpRequestTest extends AbstractSuite
{
    use InteractsWithServer;

    /**
     * @return void
     */
    public function test_get()
    {
        $response = (new Http())->get($this->url('/get'));

        $this->assertTrue($response->success());
        $this->assertSame(200, $response->status());
        $this->assertSame('GET', $response->json()['method']);
    }

    /**
     * @return void
     */
    public function test_get_with_query()
    {
        $response = (new Http())->query(['foo' => 'bar', 'baz' => '1'])->get($this->url('/get'));

        $this->assertSame(['foo' => 'bar', 'baz' => '1'], $response->json()['query']);
    }

    /**
     * @return void
     */
    public function test_base_url_prefixing()
    {
        $response = (new Http())->baseUrl($this->url())->get('/get');

        $this->assertTrue($response->success());
        $this->assertSame('/get', $response->json()['path']);
    }

    /**
     * @return void
     */
    public function test_request_headers_are_sent()
    {
        $response = (new Http())->addHeaders('X-Foo: bar')->get($this->url('/get'));

        $this->assertSame('bar', $response->json()['headers']['X-Foo']);
    }

    /**
     * @return void
     */
    public function test_post_json_body_is_encoded()
    {
        $response = (new Http())
            ->asJsonClient()
            ->post($this->url('/echo'), ['name' => 'John', 'age' => 30]);

        $this->assertStringContainsString('application/json', $response->json()['headers']['Content-Type']);
        $this->assertSame('{"name":"John","age":30}', $response->json()['body']);
    }

    /**
     * @return void
     */
    public function test_post_array_body_as_form()
    {
        $response = (new Http())->post($this->url('/echo'), ['name' => 'John']);

        $this->assertSame('POST', $response->json()['method']);
        $this->assertSame(['name' => 'John'], $response->json()['post']);
    }

    /**
     * @return void
     */
    public function test_put_and_delete_methods()
    {
        $this->assertSame('PUT', (new Http())->put($this->url('/echo'))->json()['method']);
        $this->assertSame('DELETE', (new Http())->delete($this->url('/echo'))->json()['method']);
    }

    /**
     * @return void
     */
    public function test_head_request_has_no_body()
    {
        $response = (new Http())->method('HEAD')->exec($this->url('/get'));

        $this->assertSame(200, $response->status());
        $this->assertSame('', (string) $response);
    }

    /**
     * @return void
     */
    public function test_multi_value_response_header()
    {
        $response = (new Http())->get($this->url('/multi-header'));

        $this->assertSame(['a=1', 'b=2'], $response->headers()['Set-Cookie']);
    }

    /**
     * @return void
     */
    public function test_non_2xx_status()
    {
        $response = (new Http())->get($this->url('/status/404'));

        $this->assertSame(404, $response->status());
        $this->assertFalse($response->success());
    }

    /**
     * @return void
     */
    public function test_redirect_is_followed()
    {
        $response = (new Http())->get($this->url('/redirect'));

        $this->assertSame(200, $response->status());
        $this->assertSame('/get', $response->json()['path']);
        $this->assertStringEndsWith('/get', $response->curlGetInfo()['url']);
    }

    /**
     * @return void
     */
    public function test_multi_exec_with_several_urls()
    {
        $responses = (new Http())->multiExec([$this->url('/get'), $this->url('/status/404')]);

        $this->assertCount(2, $responses);
        $this->assertTrue($responses[0]->success());
        $this->assertSame(404, $responses[1]->status());
    }

    /**
     * @return void
     */
    public function test_multi_exec_with_times()
    {
        $responses = (new Http())->multiExec($this->url('/get'), 3);

        $this->assertCount(3, $responses);
        foreach ($responses as $response) {
            $this->assertTrue($response->success());
        }
    }

    /**
     * @return void
     */
    public function test_download_saves_to_file_on_success()
    {
        $path = tempnam(sys_get_temp_dir(), 'http-download');

        try {
            $response = (new Http())->download($path)->get($this->url('/get'));

            $this->assertTrue($response->success());
            $this->assertFileExists($path);
            $this->assertSame('GET', json_decode(file_get_contents($path), true)['method']);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return void
     */
    public function test_download_removes_file_on_failure()
    {
        $path = tempnam(sys_get_temp_dir(), 'http-download');

        $response = (new Http())->download($path)->get($this->url('/status/404'));

        $this->assertFalse($response->success());
        $this->assertFileDoesNotExist($path);
    }

    /**
     * @return void
     */
    public function test_size_limit_aborts_large_responses()
    {
        $response = (new Http())->sizeLimit(10)->get($this->url('/large'));

        $this->assertFalse($response->success());
        $this->assertStringContainsString('size limit', $response->curlGetInfo()['curl_error']);
    }

    /**
     * @return void
     */
    public function test_extend_info_exposes_request_body()
    {
        $response = (new Http())
            ->extendInfo()
            ->asJsonClient()
            ->post($this->url('/echo'), ['a' => 1]);

        $this->assertSame('{"a":1}', $response->curlGetInfo()['request_body']);
    }
}
