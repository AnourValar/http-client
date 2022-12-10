<?php

namespace AnourValar\HttpClient;

class Http
{
    use \AnourValar\HttpClient\Traits\HelpersTrait;
    use \AnourValar\HttpClient\Traits\PresetsTrait;

    /**
     * Content-Type
     */
    const CONTENT_TYPE_JSON = 'application/json';
    const CONTENT_TYPE_HTML = 'text/html';
    const CONTENT_TYPE_PLAIN = 'text/plain';
    const CONTENT_TYPE_EXCEL = 'application/vnd.ms-excel';
    const CONTENT_TYPE_PDF = 'application/pdf';
    const CONTENT_TYPE_XML = 'text/xml';
    const CONTENT_TYPE_ZIP = 'application/zip';
    const CONTENT_TYPE_GZIP = 'application/gzip';
    const CONTENT_TYPE_GIF = 'image/gif';
    const CONTENT_TYPE_JPG = 'image/jpeg';
    const CONTENT_TYPE_PNG = 'image/png';

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var array
     */
    private $rememberOptions = [];

    /**
     * @param bool $defaultOptions
     * @return void
     */
    public function __construct(bool $defaultOptions = true)
    {
        if ($defaultOptions) {
            $this->applyDefaultOptions();
        }
    }

    /**
     * Remember options for all requests
     *
     * @param callable $options
     * @return self
     */
    public function remember(callable $options): self
    {
        $currOptions = $this->options;
        $this->options = [];

        $options($this);
        $this->rememberOptions = array_replace_recursive($this->rememberOptions, $this->options);

        $this->options = $currOptions;
        return $this;
    }

    /**
     * Reset all options
     *
     * @param bool $defaultOptions
     * @return self
     */
    public function reset(bool $defaultOptions = true): self
    {
        $this->options = [];
        $this->rememberOptions = [];

        if ($defaultOptions) {
            $this->applyDefaultOptions();
        }

        return $this;
    }

    /**
     * Extend info for dump
     *
     * @return self
     */
    public function extendInfo(): self
    {
        $this->options['extend_info'] = [
            'request_body' => CURLOPT_POSTFIELDS,
            'request_body_put' => CURLOPT_INFILE,
        ];

        return $this;
    }

    /**
     * Add request headers
     *
     * @param mixed $headers
     * @return self
     */
    public function addHeaders($headers): self
    {
        $headers = (array) $headers;

        foreach ($headers as $key => $value) {
            if (! is_numeric($key)) {
                $value = "$key: $value";
            }

            $key = explode(':', $value);
            $key = trim(mb_strtolower($key[0]));

            unset($this->options['curl'][CURLOPT_HTTPHEADER][$key]); // push to the end of the array
            $this->options['curl'][CURLOPT_HTTPHEADER][$key] = $value;
        }

        return $this;
    }

    /**
     * @see self::addHeaders()
     *
     * @param mixed $headers
     * @return self
     */
    public function headers($headers): self
    {
        return $this->addHeaders($headers);
    }

    /**
     * Set request method
     *
     * @param string $method
     * @return self
     */
    public function method(string $method): self
    {
        $method = mb_strtoupper($method);

        if ($method == 'HEAD') {
             $this->curlOption(CURLOPT_NOBODY, true);
             $this->curlOption(CURLOPT_CUSTOMREQUEST, null); // remove from options
        } else {
            $this->curlOption(CURLOPT_CUSTOMREQUEST, $method);
        }

        return $this;
    }

    /**
     * Set request body
     *
     * @param mixed $body
     * @return self
     */
    public function body($body): self
    {
        $this->curlOption(CURLOPT_POSTFIELDS, $body);

        return $this;
    }

    /**
     * Set curl option (native)
     *
     * @param int $name
     * @param mixed $value
     * @return self
     */
    public function curlOption(int $name, $value): self
    {
        if (is_null($value)) {
            unset($this->options['curl'][$name]);
        } else {
            $this->options['curl'][$name] = $value;
        }

        return $this;
    }

    /**
     * Set url prefix
     *
     * @param string|null $baseUrl
     * @return self
     */
    public function baseUrl(?string $baseUrl): self
    {
        if (is_null($baseUrl)) {
            unset($this->options['base_url']);
        } else {
            $this->options['base_url'] = $baseUrl;
        }

        return $this;
    }

    /**
     * Send http request
     *
     * @param string $url
     * @return \AnourValar\HttpClient\Response
     */
    public function exec(string $url): \AnourValar\HttpClient\Response
    {
        $cURL = $this->prepare($url, $options, $headers);

        $responseBody = curl_exec($cURL);
        $curlGetInfo = $this->buildCurlGetInfo($cURL, $options);

        curl_close($cURL);
        $response = new \AnourValar\HttpClient\Response($headers, $responseBody, $curlGetInfo);
        $this->handleAfter($response, $options);

        return $response;
    }

    /**
     * Send multiple http requests
     *
     * @param array $urls
     * @return array
     */
    public function multiExec(array $urls): array
    {
        $cURL = $this->prepare(null, $options);
        $mcURL = curl_multi_init();

        $cURLs = [];
        $headers = [];
        foreach ($urls as $key => $url) {
            $cURLs[$key] = curl_copy_handle($cURL);

            curl_setopt($cURLs[$key], CURLOPT_URL, $this->canonizeUrl($url, $options));
            curl_setopt($cURLs[$key], CURLOPT_HEADERFUNCTION, function ($cURL, $header) use (&$headers, $key)
            {
                if (! isset($headers[$key])) {
                    $headers[$key] = '';
                }
                $headers[$key] .= $header;

                return mb_strlen($header);
            });

            curl_multi_add_handle($mcURL, $cURLs[$key]);
        }

        do {
            $status = curl_multi_exec($mcURL, $active);
            if ($active) {
                curl_multi_select($mcURL);
            }
            curl_multi_info_read($mcURL);
        } while($active && $status == CURLM_OK);

        $result = [];

        foreach ($urls as $key => $url) {
            $responseBody = curl_multi_getcontent($cURLs[$key]);
            $curlGetInfo = $this->buildCurlGetInfo($cURLs[$key], $options);

            $result[$key] = new \AnourValar\HttpClient\Response(($headers[$key] ?? ''), $responseBody, $curlGetInfo);

            curl_multi_remove_handle($mcURL, $cURLs[$key]);
            curl_close($cURLs[$key]);
            $this->handleAfter($result[$key], $options);
        }

        curl_multi_close($mcURL);

        return $result;
    }

    /**
     * @throws \LogicException
     * @return void
     */
    protected function checkForAvailability(): void
    {
        if (defined('ARTISAN_BINARY') && config('app.env') == 'testing') {
            throw new \LogicException('Real http request detected during testing.');
        }
    }

    /**
     * @return void
     */
    private function applyDefaultOptions(): void
    {
        $this->remember(function (\AnourValar\HttpClient\Http $http)
        {
            $http
                ->curlOption(CURLOPT_ENCODING, '')
                ->curlOption(CURLOPT_FOLLOWLOCATION, true)
                ->curlOption(CURLOPT_AUTOREFERER, true)
                ->curlOption(CURLOPT_RETURNTRANSFER, true)
                ->curlOption(CURLOPT_HEADER, 0)
                ->addHeaders('Expect: ');
        });
    }

    /**
     * @param string $url
     * @param mixed $options
     * @param mixed $headers
     * @return resource
     */
    private function prepare(string $url = null, &$options = null, &$headers = null)
    {
        $this->checkForAvailability();

        $cURL = curl_init();
        $options = array_replace_recursive(['curl' => []], $this->rememberOptions, $this->options);
        $this->options = [];


        // URL
        if (! is_null($url)) {
            curl_setopt($cURL, CURLOPT_URL, $this->canonizeUrl($url, $options));
        }


        // Body specific
        if (isset($options['curl'][CURLOPT_POSTFIELDS]) &&
            is_array($options['curl'][CURLOPT_POSTFIELDS]) &&
            isset($options['curl'][CURLOPT_HTTPHEADER]) &&
            in_array('Content-Type: '.self::CONTENT_TYPE_JSON, (array) $options['curl'][CURLOPT_HTTPHEADER])
        ) {
            $options['curl'][CURLOPT_POSTFIELDS] = json_encode($options['curl'][CURLOPT_POSTFIELDS]);
        }


        // Method specific
        $method = ( $options['curl'][CURLOPT_CUSTOMREQUEST] ?? null );

        if ($method == 'POST') {
            if (isset($options['curl'][CURLOPT_USERAGENT])) {
                curl_setopt($cURL, CURLOPT_POST, 1);
            }

            curl_setopt($cURL, CURLOPT_POSTREDIR, 1|2|4);
        }


        // Options
        curl_setopt_array($cURL, $options['curl']);


        // Size limit
        if (! empty($options['size_limit'])) {
            curl_setopt($cURL, CURLOPT_BUFFERSIZE, 256);
            curl_setopt($cURL, CURLOPT_NOPROGRESS, false);
            curl_setopt(
                $cURL,
                CURLOPT_PROGRESSFUNCTION,
                function($cURL, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($options) {
                    if ($downloadSize > ($options['size_limit'] * 1024)) {
                        return 1; // non-0 breaks the connection
                    }
                }
            );
        }


        // Etc
        curl_setopt($cURL, CURLINFO_HEADER_OUT, true);

        if (! is_null($url)) {
            curl_setopt($cURL, CURLOPT_HEADERFUNCTION, function ($cURL, $header) use (&$headers)
            {
                $headers .= $header;
                return mb_strlen($header);
            });
        }


        return $cURL;
    }

    /**
     * @param string $url
     * @param array $options
     * @return string
     */
    private function canonizeUrl(string $url, array $options): string
    {
        if (isset($options['base_url'])) {
            $url = $options['base_url'] . $url;
        }

        return str_replace(' ', '%20', $url);
    }

    /**
     * @param resource $cURL
     * @param array $options
     * @return array
     */
    private function buildCurlGetInfo($cURL, array $options): array
    {
        $result = curl_getinfo($cURL);

        $result['curl_error'] = curl_error($cURL);
        if (! $result['curl_error']) {
            unset($result['curl_error']);
        }

        foreach (( $options['extend_info'] ?? [] ) as $key => $value) {
            if (isset($options['curl'][$value])) {
                $result[$key] = $options['curl'][$value];

                if (is_resource($result[$key])) {
                    rewind($result[$key]);
                    $result[$key] = stream_get_contents($result[$key]);
                }
            }
        }

        if (
            isset($result['curl_error'])
            && isset($options['size_limit'])
            && stripos($result['curl_error'], 'callback aborted') !== false
        ) {
            $result['http_code'] = 0;
            $result['curl_error'] .= " (due to size limit: {$options['size_limit']} kB)";
        }

        return $result;
    }

    /**
     * @param \AnourValar\HttpClient\Response $response
     * @param array $options
     * @return void
     */
    private function handleAfter(\AnourValar\HttpClient\Response $response, array $options): void
    {
        if (isset($options['curl'][CURLOPT_FILE]) && is_resource($options['curl'][CURLOPT_FILE])) {
            $file = stream_get_meta_data($options['curl'][CURLOPT_FILE])['uri'];
            fclose($options['curl'][CURLOPT_FILE]);

            if (!$response->success() && is_file($file)) {
                unlink($file);
            }
        }

        if (isset($options['curl'][CURLOPT_INFILE]) && is_resource($options['curl'][CURLOPT_INFILE])) {
            fclose($options['curl'][CURLOPT_INFILE]);
        }
    }
}
