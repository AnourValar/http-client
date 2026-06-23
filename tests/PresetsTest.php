<?php

namespace AnourValar\HttpClient\Tests;

use AnourValar\HttpClient\Http;
use AnourValar\HttpClient\Tests\Concerns\ReadsHttpOptions;

class PresetsTest extends AbstractSuite
{
    use ReadsHttpOptions;

    /**
     * @return void
     */
    public function test_size_limit()
    {
        $http = (new Http(false))->sizeLimit(128);
        $this->assertSame(128, $this->metaOptions($http)['size_limit']);

        $http->sizeLimit();
        $this->assertArrayNotHasKey('size_limit', $this->metaOptions($http));

        $http->sizeLimit(128)->sizeLimit(0);
        $this->assertArrayNotHasKey('size_limit', $this->metaOptions($http));
    }

    /**
     * @return void
     */
    public function test_as_browser()
    {
        $http = (new Http(false))->asBrowser();
        $this->assertStringContainsString('Mozilla/5.0', $this->curlOptions($http)[CURLOPT_USERAGENT]);

        $http = (new Http(false))->asBrowser('MyAgent/1.0');
        $this->assertSame('MyAgent/1.0', $this->curlOptions($http)[CURLOPT_USERAGENT]);
    }

    /**
     * @return void
     */
    public function test_as_json_client()
    {
        $headers = $this->curlOptions((new Http(false))->asJsonClient())[CURLOPT_HTTPHEADER];
        $this->assertSame('Accept: application/json', $headers['accept']);
        $this->assertSame('Content-Type: application/json', $headers['content-type']);

        $headers = $this->curlOptions((new Http(false))->asJsonClient(true, false))[CURLOPT_HTTPHEADER];
        $this->assertArrayHasKey('accept', $headers);
        $this->assertArrayNotHasKey('content-type', $headers);

        $headers = $this->curlOptions((new Http(false))->asJsonClient(false, true))[CURLOPT_HTTPHEADER];
        $this->assertArrayNotHasKey('accept', $headers);
        $this->assertArrayHasKey('content-type', $headers);
    }

    /**
     * @return void
     */
    public function test_auth_basic()
    {
        $curl = $this->curlOptions((new Http(false))->authBasic('user', 'pass'));

        $this->assertSame(CURLAUTH_BASIC, $curl[CURLOPT_HTTPAUTH]);
        $this->assertSame('user:pass', $curl[CURLOPT_USERPWD]);
    }

    /**
     * @return void
     */
    public function test_auth_digest()
    {
        $curl = $this->curlOptions((new Http(false))->authDigest('user', 'pass'));

        $this->assertSame(CURLAUTH_DIGEST, $curl[CURLOPT_HTTPAUTH]);
        $this->assertSame('user:pass', $curl[CURLOPT_USERPWD]);
    }

    /**
     * @return void
     */
    public function test_auth_token()
    {
        $headers = $this->curlOptions((new Http(false))->authToken('abc'))[CURLOPT_HTTPHEADER];
        $this->assertSame('Authorization: Bearer abc', $headers['authorization']);

        $headers = $this->curlOptions((new Http(false))->authToken('abc', 'Token'))[CURLOPT_HTTPHEADER];
        $this->assertSame('Authorization: Token abc', $headers['authorization']);
    }

    /**
     * @return void
     */
    public function test_ignore_ssl()
    {
        $curl = $this->curlOptions((new Http(false))->ignoreSsl());

        $this->assertSame(0, $curl[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $curl[CURLOPT_SSL_VERIFYHOST]);
    }

    /**
     * @return void
     */
    public function test_proxy()
    {
        $curl = $this->curlOptions((new Http(false))->proxy('127.0.0.1:8080', 'user:pass'));
        $this->assertSame('127.0.0.1:8080', $curl[CURLOPT_PROXY]);
        $this->assertSame('user:pass', $curl[CURLOPT_PROXYUSERPWD]);

        // Without credentials the user/password option is not set
        $curl = $this->curlOptions((new Http(false))->proxy('127.0.0.1:8080'));
        $this->assertSame('127.0.0.1:8080', $curl[CURLOPT_PROXY]);
        $this->assertArrayNotHasKey(CURLOPT_PROXYUSERPWD, $curl);
    }

    /**
     * @return void
     */
    public function test_cookies()
    {
        $curl = $this->curlOptions((new Http(false))->cookies('a=1; b=2'));

        $this->assertSame('a=1; b=2', $curl[CURLOPT_COOKIE]);
    }

    /**
     * @return void
     */
    public function test_cookies_file()
    {
        $curl = $this->curlOptions((new Http(false))->cookiesFile('/tmp/cookies.txt'));

        $this->assertSame('/tmp/cookies.txt', $curl[CURLOPT_COOKIEFILE]);
        $this->assertSame('/tmp/cookies.txt', $curl[CURLOPT_COOKIEJAR]);
    }

    /**
     * @return void
     */
    public function test_timeouts()
    {
        $curl = $this->curlOptions((new Http(false))->timeouts(100, 200));
        $this->assertSame(100, $curl[CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(200, $curl[CURLOPT_TIMEOUT_MS]);

        // No arguments removes both
        $curl = $this->curlOptions((new Http(false))->timeouts());
        $this->assertArrayNotHasKey(CURLOPT_CONNECTTIMEOUT_MS, $curl);
        $this->assertArrayNotHasKey(CURLOPT_TIMEOUT_MS, $curl);
    }

    /**
     * @return void
     */
    public function test_referer()
    {
        $curl = $this->curlOptions((new Http(false))->referer('https://example.test/a b'));
        $this->assertSame('https://example.test/a%20b', $curl[CURLOPT_REFERER]);

        // Alias
        $curl = $this->curlOptions((new Http(false))->referrer('https://example.test/c'));
        $this->assertSame('https://example.test/c', $curl[CURLOPT_REFERER]);
    }

    /**
     * @return void
     */
    public function test_file()
    {
        $file = (new Http(false))->file('/tmp/avatar.png', 'image/png', 'avatar.png');

        $this->assertInstanceOf(\CURLFile::class, $file);
        $this->assertSame('/tmp/avatar.png', $file->getFilename());
        $this->assertSame('image/png', $file->getMimeType());
        $this->assertSame('avatar.png', $file->getPostFilename());
    }

    /**
     * @return void
     */
    public function test_string_file()
    {
        $file = (new Http(false))->stringFile('the-content', 'text/plain', 'data.txt');

        $this->assertInstanceOf(\CURLStringFile::class, $file);
        $this->assertSame('the-content', $file->data);
        $this->assertSame('text/plain', $file->mime);
        $this->assertSame('data.txt', $file->postname);
    }

    /**
     * @return void
     */
    public function test_put_string_file()
    {
        $http = (new Http(false))->putStringFile('payload');
        $curl = $this->curlOptions($http);

        $this->assertSame('PUT', $curl[CURLOPT_CUSTOMREQUEST]);
        $this->assertSame('payload', $curl[CURLOPT_POSTFIELDS]);
    }

    /**
     * @return void
     */
    public function test_put_file()
    {
        $path = tempnam(sys_get_temp_dir(), 'http');
        file_put_contents($path, 'file-payload');

        try {
            $http = (new Http(false))->putFile($path);
            $curl = $this->curlOptions($http);

            $this->assertSame('PUT', $curl[CURLOPT_CUSTOMREQUEST]);
            $this->assertSame(1, $curl[CURLOPT_PUT]);
            $this->assertSame(strlen('file-payload'), $curl[CURLOPT_INFILESIZE]);
            $this->assertIsResource($curl[CURLOPT_INFILE]);

            fclose($curl[CURLOPT_INFILE]);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return void
     */
    public function test_form_multipart_with_scalar()
    {
        $http = new Http(false);
        $body = $http->formMultipart(['field' => 'value']);

        $curl = $this->curlOptions($http);
        $this->assertStringContainsString('multipart/form-data; boundary=', $curl[CURLOPT_HTTPHEADER]['content-type']);
        $this->assertSame($body, $curl[CURLOPT_POSTFIELDS]);

        $this->assertStringContainsString('Content-Disposition: form-data; name="field"', $body);
        $this->assertStringContainsString('value', $body);
    }

    /**
     * @return void
     */
    public function test_form_multipart_with_string_file()
    {
        $http = new Http(false);
        $body = $http->formMultipart([
            'document' => $http->stringFile('binary-data', 'application/pdf', 'doc.pdf'),
        ]);

        $this->assertStringContainsString('name="document"; filename="doc.pdf"', $body);
        $this->assertStringContainsString('Content-Type: application/pdf', $body);
        $this->assertStringContainsString('binary-data', $body);
    }

    /**
     * @return void
     */
    public function test_form_multipart_with_curl_file()
    {
        $path = tempnam(sys_get_temp_dir(), 'http');
        file_put_contents($path, 'real-file-data');

        try {
            $http = new Http(false);
            $body = $http->formMultipart([
                'upload' => $http->file($path, 'text/plain', 'upload.txt'),
            ]);

            $this->assertStringContainsString('name="upload"; filename="upload.txt"', $body);
            $this->assertStringContainsString('Content-Type: text/plain', $body);
            $this->assertStringContainsString('real-file-data', $body);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return void
     */
    public function test_form_multipart_escapes_special_characters()
    {
        $http = new Http(false);
        $body = $http->formMultipart(['na"me' => 'value']);

        $this->assertStringContainsString('name="na%22me"', $body);
    }
}
