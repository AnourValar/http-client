<?php

namespace AnourValar\HttpClient\Tests;

use AnourValar\HttpClient\Http;
use AnourValar\HttpClient\Tests\Concerns\ReadsHttpOptions;

class HttpOptionsTest extends AbstractSuite
{
    use ReadsHttpOptions;

    /**
     * @return void
     */
    public function test_default_options()
    {
        $curl = $this->curlOptions(new Http());

        $this->assertSame('', $curl[CURLOPT_ENCODING]);
        $this->assertTrue($curl[CURLOPT_FOLLOWLOCATION]);
        $this->assertTrue($curl[CURLOPT_AUTOREFERER]);
        $this->assertTrue($curl[CURLOPT_RETURNTRANSFER]);
        $this->assertSame(0, $curl[CURLOPT_HEADER]);
        $this->assertSame('Expect: ', $curl[CURLOPT_HTTPHEADER]['expect']);
    }

    /**
     * @return void
     */
    public function test_construct_without_default_options()
    {
        $http = new Http(false);

        $this->assertSame([], $this->rememberedOptions($http));
        $this->assertSame([], $this->rawOptions($http));
    }

    /**
     * @return void
     */
    public function test_add_headers_variants()
    {
        $http = (new Http(false))
            ->addHeaders('X-String: a')
            ->addHeaders(['X-Assoc' => 'b'])
            ->addHeaders(['X-Numeric: c']);

        $headers = $this->curlOptions($http)[CURLOPT_HTTPHEADER];

        $this->assertSame('X-String: a', $headers['x-string']);
        $this->assertSame('X-Assoc: b', $headers['x-assoc']);
        $this->assertSame('X-Numeric: c', $headers['x-numeric']);
    }

    /**
     * @return void
     */
    public function test_add_headers_overrides_and_moves_to_the_end()
    {
        $http = (new Http(false))
            ->addHeaders('X-A: 1')
            ->addHeaders('X-B: 2')
            ->addHeaders('X-A: 3');

        $headers = $this->curlOptions($http)[CURLOPT_HTTPHEADER];

        // The duplicate is overridden ...
        $this->assertSame('X-A: 3', $headers['x-a']);
        $this->assertCount(2, $headers);
        // ... and re-positioned to the end of the list
        $this->assertSame(['x-b', 'x-a'], array_keys($headers));
    }

    /**
     * @return void
     */
    public function test_headers_is_an_alias_for_add_headers()
    {
        $http = (new Http(false))->headers('X-A: 1');

        $this->assertSame('X-A: 1', $this->curlOptions($http)[CURLOPT_HTTPHEADER]['x-a']);
    }

    /**
     * @return void
     */
    public function test_method()
    {
        $http = (new Http(false))->method('post');
        $this->assertSame('POST', $this->curlOptions($http)[CURLOPT_CUSTOMREQUEST]);

        $http = (new Http(false))->method('Delete');
        $this->assertSame('DELETE', $this->curlOptions($http)[CURLOPT_CUSTOMREQUEST]);
    }

    /**
     * @return void
     */
    public function test_method_head_is_special()
    {
        $http = (new Http(false))->method('HEAD');
        $curl = $this->curlOptions($http);

        $this->assertTrue($curl[CURLOPT_NOBODY]);
        $this->assertArrayNotHasKey(CURLOPT_CUSTOMREQUEST, $curl);
    }

    /**
     * @return void
     */
    public function test_body()
    {
        $http = (new Http(false))->body(['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $this->curlOptions($http)[CURLOPT_POSTFIELDS]);
    }

    /**
     * @return void
     */
    public function test_curl_option_set_and_unset()
    {
        $http = (new Http(false))->curlOption(CURLOPT_TIMEOUT, 10);
        $this->assertSame(10, $this->curlOptions($http)[CURLOPT_TIMEOUT]);

        $http->curlOption(CURLOPT_TIMEOUT, null);
        $this->assertArrayNotHasKey(CURLOPT_TIMEOUT, $this->curlOptions($http));
    }

    /**
     * @return void
     */
    public function test_base_url()
    {
        $http = (new Http(false))->baseUrl('https://example.test');
        $this->assertSame('https://example.test', $this->metaOptions($http)['base_url']);

        $http->baseUrl(null);
        $this->assertArrayNotHasKey('base_url', $this->metaOptions($http));
    }

    /**
     * @return void
     */
    public function test_query()
    {
        $http = (new Http(false))->query(['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $this->metaOptions($http)['query']);

        $http->query(null);
        $this->assertArrayNotHasKey('query', $this->metaOptions($http));
    }

    /**
     * @return void
     */
    public function test_extend_info()
    {
        $http = (new Http(false))->extendInfo();

        $this->assertSame(
            ['request_body' => CURLOPT_POSTFIELDS, 'request_body_put' => CURLOPT_INFILE],
            $this->metaOptions($http)['extend_info']
        );
    }

    /**
     * @return void
     */
    public function test_remember()
    {
        $http = new Http(false);
        $http->remember(function (Http $http) {
            $http->addHeaders('X-Remembered: 1');
        });

        // It lands in the remembered bag ...
        $this->assertSame('X-Remembered: 1', $this->rememberedOptions($http)['curl'][CURLOPT_HTTPHEADER]['x-remembered']);
        // ... and does not pollute the staged options
        $this->assertSame([], $this->rawOptions($http));
    }

    /**
     * @return void
     */
    public function test_reset()
    {
        $http = (new Http())->addHeaders('X-A: 1')->baseUrl('https://example.test');

        $http->reset(false);
        $this->assertSame([], $this->rawOptions($http));
        $this->assertSame([], $this->rememberedOptions($http));

        $http->addHeaders('X-A: 1');
        $http->reset(true);
        // Defaults are re-applied
        $this->assertTrue($this->curlOptions($http)[CURLOPT_RETURNTRANSFER]);
        $this->assertSame([], $this->rawOptions($http));
    }

    /**
     * @return void
     */
    public function test_fluent_interface_returns_self()
    {
        $http = new Http(false);

        $this->assertSame($http, $http->addHeaders('X-A: 1'));
        $this->assertSame($http, $http->method('GET'));
        $this->assertSame($http, $http->body('x'));
        $this->assertSame($http, $http->curlOption(CURLOPT_TIMEOUT, 1));
        $this->assertSame($http, $http->baseUrl('https://example.test'));
        $this->assertSame($http, $http->query(['a' => 1]));
        $this->assertSame($http, $http->reset());
    }
}
