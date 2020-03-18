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
     * @param boolean $defaultOptions
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
    public function remember(callable $options) : self
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
     * @param boolean $defaultOptions
     * @return self
     */
    public function reset(bool $defaultOptions = true) : self
    {
        $this->after();
        $this->rememberOptions = [];

        if ($defaultOptions) {
            $this->applyDefaultOptions();
        }

        return $this;
    }

    /**
     * Add request headers
     *
     * @param mixed $headers
     * @return self
     */
    public function addHeaders($headers) : self
    {
        $headers = (array)$headers;

        foreach ($headers as $key => $value) {
            if (! is_numeric($key)) {
                $value = "$key: $value";
            }

            $key = explode(':', $value);
            $key = trim(mb_strtolower($key[0]));

            unset($this->options[CURLOPT_HTTPHEADER][$key]); // push to the end of the array
            $this->options[CURLOPT_HTTPHEADER][$key] = $value;
        }

        return $this;
    }

    /**
     * Set request method
     *
     * @param string $method
     * @return self
     */
    public function method(string $method) : self
    {
        $this->curlOption(CURLOPT_CUSTOMREQUEST, mb_strtoupper($method));

        return $this;
    }

    /**
     * Set request body
     *
     * @param mixed $body
     * @return self
     */
    public function body($body) : self
    {
        if (is_array($body) &&
            isset($this->options[CURLOPT_HTTPHEADER]) &&
            in_array('Content-Type: '.self::CONTENT_TYPE_JSON, (array)$this->options[CURLOPT_HTTPHEADER])
        ) {
            $body = json_encode($body);
        }

        $this->curlOption(CURLOPT_POSTFIELDS, $body);

        return $this;
    }

    /**
     * Set curl option (native)
     *
     * @param integer $name
     * @param mixed $value
     * @return self
     */
    public function curlOption(int $name, $value) : self
    {
        if (is_null($value)) {
            unset($this->options[$name]);
        } else {
            $this->options[$name] = $value;
        }

        return $this;
    }

    /**
     * Upload file
     *
     * @param string $filename
     * @param string $mimetype
     * @param string $postname
     * @return \CurlFile
     */
    public function attach(string $filename, string $mimetype = null, string $postname = null) : \CurlFile
    {
        return new \CurlFile($filename, $mimetype, $postname);
    }

    /**
     * Send http request
     *
     * @param string $url
     * @return \AnourValar\HttpClient\Response
     */
    public function exec(string $url) : \AnourValar\HttpClient\Response
    {
        $cURL = $this->before($url, $headers);

        $responseBody = curl_exec($cURL);
        $curlGetInfo = $this->buildCurlGetInfo($cURL);

        $this->after();
        curl_close($cURL);

        return new \AnourValar\HttpClient\Response($headers, $responseBody, $curlGetInfo);
    }

    /**
     * Send multiple http requests
     *
     * @param array $urls
     * @return array
     */
    public function multiExec(array $urls) : array
    {
        $cURL = $this->before();
        $mcURL = curl_multi_init();

        $cURLs = [];
        $headers = [];
        foreach ($urls as $key => $url) {
            $cURLs[$key] = curl_copy_handle($cURL);

            curl_setopt($cURLs[$key], CURLOPT_URL, $this->canonizeUrl($url));
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
            curl_multi_exec($mcURL, $active);
            curl_multi_select($mcURL);
        } while($active > 0);

        $result = [];

        foreach ($urls as $key => $url) {
            $responseBody = curl_multi_getcontent($cURLs[$key]);
            $curlGetInfo = $this->buildCurlGetInfo($cURLs[$key]);

            $result[$key] = new \AnourValar\HttpClient\Response($headers[$key], $responseBody, $curlGetInfo);

            curl_multi_remove_handle($mcURL, $cURLs[$key]);
            curl_close($cURLs[$key]);
        }

        $this->after();
        curl_multi_close($mcURL);

        return $result;
    }

    /**
     * Send http request and save response body to the file
     *
     * @param string $url
     * @param string $file
     * @return \AnourValar\HttpClient\Response
     */
    public function download(string $url, string $file) : \AnourValar\HttpClient\Response
    {
        $cURL = $this->before($url, $headers);

        $fp = fopen($file, 'w+');
        curl_setopt($cURL, CURLOPT_FILE, $fp);

        $responseBody = curl_exec($cURL);
        $curlGetInfo = $this->buildCurlGetInfo($cURL);

        $this->after();
        curl_close($cURL);
        fclose($fp);

        $mapper = new \AnourValar\HttpClient\Response($headers, $responseBody, $curlGetInfo);
        if (!$mapper->success() && is_file($file)) {
            unlink($file);
        }

        return $mapper;
    }

    /**
     * @return void
     */
    private function applyDefaultOptions() : void
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
     * @param mixed $headers
     * @return resource
     */
    private function before(string $url = null, &$headers = null)
    {
        $cURL = curl_init();
        $options = array_replace_recursive($this->rememberOptions, $this->options);


        // URL
        if (! is_null($url)) {
            curl_setopt($cURL, CURLOPT_URL, $this->canonizeUrl($url));
        }


        // Method specific
        $method = ( $options[CURLOPT_CUSTOMREQUEST] ?? null );

        if ($method == 'POST') {
            if (isset($options[CURLOPT_USERAGENT])) {
                curl_setopt($cURL, CURLOPT_POST, 1);
            }

            curl_setopt($cURL, CURLOPT_POSTREDIR, 1|2|4);
        } else if ($method == 'PUT') {
            curl_setopt($cURL, CURLOPT_PUT, 1);
        }


        // Options
        curl_setopt_array($cURL, $options);


        // Etc
        curl_setopt($cURL, CURLINFO_HEADER_OUT, true);

        if (count(func_get_args()) > 1) {
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
     * @return string
     */
    private function canonizeUrl(string $url) : string
    {
        return str_replace(' ', '%20', $url);
    }

    /**
     * @return void
     */
    private function after() : void
    {
        $this->options = [];
    }

    /**
     * @param resource $cURL
     * @return array
     */
    private function buildCurlGetInfo($cURL) : array
    {
        $result = curl_getinfo($cURL);

        $result['curl_error'] = curl_error($cURL);
        if (! $result['curl_error']) {
            unset($result['curl_error']);
        }

        return $result;
    }
}